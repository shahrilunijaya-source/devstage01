<?php

declare(strict_types=1);

namespace App\Services\Document;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Slide;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Color;

/**
 * Renders a stakeholder review deck (PRD §13) to a native PowerPoint (.pptx)
 * from the same DeckBuilder slide data used by the HTML deck. Returns a path to
 * a temporary file the caller streams and deletes.
 */
class PptxExporter
{
    private const ACCENT = 'FF00A6A6';   // teal

    private const INK = 'FF1F2937';      // near-black

    private const MUTED = 'FF6B7280';    // grey

    private const WARN = 'FFC9982A';     // amber

    /**
     * @param  array<string, mixed>  $deck  output of DeckBuilder::build()
     * @return string absolute path to the generated .pptx
     */
    public function export(array $deck): string
    {
        $ppt = new PhpPresentation;

        $this->titleSlide($ppt->getActiveSlide(), $deck);
        $this->summarySlide($ppt->createSlide(), $deck['summary']);

        foreach ($deck['sections'] as $section) {
            $this->sectionSlide($ppt->createSlide(), $section, $deck['stageLabel']);
        }

        if ($deck['risks']->isNotEmpty()) {
            $this->risksSlide($ppt->createSlide(), $deck['risks']);
        }

        return $this->write($ppt, $deck['baseline']->version_label);
    }

    /** @param  array<string, mixed>  $deck */
    private function titleSlide(Slide $slide, array $deck): void
    {
        $baseline = $deck['baseline'];
        $project = $deck['project'];

        $this->heading($slide, 'Stage Review Deck', 60, self::ACCENT, 14, true);
        $this->text($slide, $baseline->version_label, 60, 140, 44, self::INK, true, 36);
        $this->text(
            $slide,
            "{$project->tenant?->name} · {$project->name}\n{$deck['stageLabel']} stage · Knowledge Book ".($baseline->knowledge_book_version ?? '—'),
            60,
            260,
            18,
            self::MUTED,
        );
    }

    /** @param  array<string, mixed>  $summary */
    private function summarySlide(Slide $slide, array $summary): void
    {
        $this->heading($slide, 'Baseline summary', 50, self::ACCENT);
        $lines = sprintf(
            "%d objects\n%d requirements\n%d open risk(s)\n%d evidence-confirmed\n%d object types",
            $summary['total'],
            $summary['requirements'],
            $summary['risks'],
            $summary['confirmed'],
            $summary['types'],
        );
        $this->text($slide, $lines, 60, 130, 22, self::INK, false, 22);
    }

    /** @param  array<string, mixed>  $section */
    private function sectionSlide(Slide $slide, array $section, string $stageLabel): void
    {
        $this->heading($slide, $section['heading'], 50, self::ACCENT);

        $items = $section['items']->take(6)
            ->map(fn (array $i): string => '• '.$i['ref'].' — '.Str::limit($i['title'], 90))
            ->implode("\n");

        if ($section['items']->count() > 6) {
            $items .= "\n+ ".($section['items']->count() - 6).' more in the full document';
        }

        $this->text($slide, $items, 60, 130, 16, self::INK, false, 16);
    }

    /** @param  Collection<int, array<string, mixed>>  $risks */
    private function risksSlide(Slide $slide, $risks): void
    {
        $this->heading($slide, 'Open risks', 50, self::WARN);

        $items = $risks->take(6)
            ->map(fn (array $r): string => '• '.$r['ref'].' — '.Str::limit($r['title'], 90))
            ->implode("\n");

        $this->text($slide, $items, 60, 130, 16, self::INK, false, 16);
    }

    private function heading(Slide $slide, string $text, int $offsetY, string $color, int $size = 12, bool $upper = false): void
    {
        $this->text($slide, $upper ? strtoupper($text) : $text, 60, $offsetY, $size, $color, true, $size);
    }

    private function text(Slide $slide, string $text, int $x, int $y, int $size, string $color, bool $bold = false, int $lineHeight = 18): void
    {
        $shape = $slide->createRichTextShape();
        $shape->setHeight(max(40, $lineHeight * (substr_count($text, "\n") + 2)))
            ->setWidth(820)
            ->setOffsetX($x)
            ->setOffsetY($y);
        $shape->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        foreach (explode("\n", $text) as $i => $line) {
            if ($i > 0) {
                $shape->createParagraph();
            }
            $run = $shape->createTextRun($line);
            $run->getFont()->setBold($bold)->setSize($size)->setColor(new Color($color));
        }
    }

    private function write(PhpPresentation $ppt, string $label): string
    {
        $dir = storage_path('app/tmp');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = $dir.'/'.Str::slug($label).'-'.Str::random(8).'.pptx';

        IOFactory::createWriter($ppt, 'PowerPoint2007')->save($path);

        return $path;
    }
}
