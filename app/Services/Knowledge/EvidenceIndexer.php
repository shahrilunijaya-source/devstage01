<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Enums\ObjectType;
use App\Models\Graph\EngObject;
use App\Models\RagChunk;
use App\Models\SystemSetting;
use App\Services\Rag\Chunker;
use App\Services\Rag\VoyageClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser;

/**
 * Indexes session evidence into the RAG substrate so it is retrievable alongside
 * project documents and knowledge (PRD §7.5, §8). Evidence enters as immutable
 * source — once captured it can be searched across the project's whole corpus.
 *
 * Reuses the existing Chunker + Voyage pipeline. Chunks are tenant-isolated and
 * scope='project', so retrieval stays bound by the same project_id access
 * boundary as every other chunk. Best-effort: when no Voyage key is configured
 * the call is a silent no-op, so evidence capture never depends on embeddings.
 */
class EvidenceIndexer
{
    private const SOURCE_TYPE = 'evidence';

    /** MIME prefixes/types whose stored file content is safe to read as text. */
    private const TEXT_MIME = ['text/', 'application/json', 'application/csv'];

    public function __construct(
        private readonly Chunker $chunker,
        private readonly VoyageClient $voyage,
    ) {}

    /**
     * (Re)index one EVIDENCE object. Returns the number of chunks stored (0 when
     * skipped: not evidence, no extractable text, or embeddings unavailable).
     */
    public function index(EngObject $evidence): int
    {
        if ($evidence->type !== ObjectType::EVIDENCE) {
            return 0;
        }

        // Embeddings off → skip rather than throw, so capture stays resilient.
        if (blank(SystemSetting::get('voyage_api_key'))) {
            return 0;
        }

        $text = $this->extractText($evidence);
        if ($text === '') {
            return 0;
        }

        $pieces = $this->chunker->chunk($text);
        if ($pieces === []) {
            return 0;
        }

        $label = trim("Evidence {$evidence->ref}: ".(string) $evidence->title);
        $vectors = $this->voyage->embed(array_map(fn ($p) => $p['text'], $pieces), 'document');

        DB::transaction(function () use ($evidence, $pieces, $vectors, $label): void {
            $this->deleteFor($evidence);

            foreach ($pieces as $i => $piece) {
                RagChunk::create([
                    'project_id' => $evidence->project_id,
                    'tenant_id' => $evidence->tenant_id,
                    'scope' => 'project',
                    'source_type' => self::SOURCE_TYPE,
                    'source_id' => $evidence->id,
                    'source_label' => $label,
                    'chunk_text' => $piece['text'],
                    'embedding' => $vectors[$i] ?? [],
                    'token_count' => $piece['tokens'],
                    'content_hash' => hash('sha256', self::SOURCE_TYPE.'|'.$evidence->id.'|'.$piece['text']),
                ]);
            }
        });

        return count($pieces);
    }

    /** Drop all chunks for an evidence object (e.g. before re-index or on delete). */
    public function deleteFor(EngObject $evidence): void
    {
        RagChunk::query()
            ->where('project_id', $evidence->project_id)
            ->where('source_type', self::SOURCE_TYPE)
            ->where('source_id', $evidence->id)
            ->delete();
    }

    /**
     * Pull indexable text from an evidence object: its body for pasted evidence,
     * the stored file's contents for a readable text type, or extracted text for
     * a PDF / Word document (when the parser is available). Other binary formats
     * are skipped.
     */
    private function extractText(EngObject $evidence): string
    {
        if (filled($evidence->body)) {
            return trim((string) $evidence->body);
        }

        // 'attributes' is a reserved Eloquent property; read the JSON column via
        // getAttribute so the array cast applies instead of the raw attribute bag.
        $attributes = $evidence->getAttribute('attributes') ?? [];
        $path = $attributes['path'] ?? null;
        $mime = (string) ($attributes['mime'] ?? '');

        if ($path === null) {
            return '';
        }

        try {
            if ($this->isTextMime($mime)) {
                return trim((string) Storage::get($path));
            }

            if ($mime === 'application/pdf' && class_exists(Parser::class)) {
                $bytes = Storage::get($path);

                return $bytes === null ? '' : trim((new Parser)->parseContent($bytes)->getText());
            }

            if ($this->isWordMime($mime) && class_exists(IOFactory::class)) {
                return $this->extractWord($path);
            }
        } catch (\Throwable) {
            return ''; // unreadable/corrupt file → skip indexing, never break capture
        }

        return '';
    }

    private function isWordMime(string $mime): bool
    {
        return in_array($mime, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
        ], true);
    }

    /** Extract visible text from a .docx via PhpWord (loads from a temp file). */
    private function extractWord(string $path): string
    {
        $bytes = Storage::get($path);
        if ($bytes === null) {
            return '';
        }

        $tmp = tempnam(sys_get_temp_dir(), 'evd');
        file_put_contents($tmp, $bytes);

        try {
            $doc = IOFactory::load($tmp, 'Word2007');
            $out = [];
            foreach ($doc->getSections() as $section) {
                $this->collectWordText($section->getElements(), $out);
            }

            return trim(implode(' ', $out));
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Recursively collect text from PhpWord elements (Text + nested TextRun, etc).
     *
     * @param  array<int, object>  $elements
     * @param  array<int, string>  $out
     */
    private function collectWordText(array $elements, array &$out): void
    {
        foreach ($elements as $el) {
            if (method_exists($el, 'getText')) {
                $text = $el->getText();
                if (is_string($text) && $text !== '') {
                    $out[] = $text;
                }
            }
            if (method_exists($el, 'getElements')) {
                $this->collectWordText($el->getElements(), $out);
            }
        }
    }

    private function isTextMime(string $mime): bool
    {
        foreach (self::TEXT_MIME as $allowed) {
            if (str_starts_with($mime, $allowed)) {
                return true;
            }
        }

        return false;
    }
}
