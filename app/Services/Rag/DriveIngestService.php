<?php

namespace App\Services\Rag;

use App\Models\SystemSetting;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Google\Service\Drive\DriveFile;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\IOFactory;
use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Reads project documents from Google Drive using a service account, and extracts
 * plain text from PDFs, .docx, and native Google Docs. Scanned/image-only PDFs
 * yield no text and are reported as unindexable (OCR is out of scope for v1).
 */
class DriveIngestService
{
    /**
     * Build a Drive client from the service-account JSON stored in admin Settings.
     */
    public function drive(): GoogleDrive
    {
        $json = SystemSetting::get('gdrive_service_account_json');
        if (blank($json)) {
            throw new RuntimeException('Google Drive service account is not configured.');
        }

        $config = json_decode($json, true);
        if (! is_array($config)) {
            throw new RuntimeException('Google Drive service account JSON is invalid.');
        }

        $client = new GoogleClient;
        $client->setAuthConfig($config);
        $client->setScopes([GoogleDrive::DRIVE_READONLY]);

        return new GoogleDrive($client);
    }

    /**
     * Pull the Drive folder id out of a gdrive_link (…/folders/{id}, ?id={id}, or a
     * bare id). Returns null if nothing usable is found.
     */
    public function folderIdFromLink(?string $link): ?string
    {
        if (blank($link)) {
            return null;
        }

        if (preg_match('#/folders/([a-zA-Z0-9_-]+)#', $link, $m)) {
            return $m[1];
        }
        if (preg_match('#[?&]id=([a-zA-Z0-9_-]+)#', $link, $m)) {
            return $m[1];
        }
        if (preg_match('#^[a-zA-Z0-9_-]{20,}$#', trim($link))) {
            return trim($link);
        }

        return null;
    }

    /**
     * List non-trashed files in a folder, descending recursively into subfolders.
     * Folder entries themselves are not returned (they hold no text); their
     * contents are flattened into the result. A seen-folder set guards against a
     * subfolder shared under two parents being walked twice.
     *
     * @return array<int, DriveFile>
     */
    public function listFiles(string $folderId): array
    {
        $drive = $this->drive();
        $seenFolders = [];

        return $this->collectFiles($drive, $folderId, $seenFolders);
    }

    /**
     * @param  array<string, true>  $seenFolders
     * @return array<int, DriveFile>
     */
    private function collectFiles(GoogleDrive $drive, string $folderId, array &$seenFolders): array
    {
        if (isset($seenFolders[$folderId])) {
            return [];
        }
        $seenFolders[$folderId] = true;

        $files = [];
        $pageToken = null;

        do {
            $resp = $drive->files->listFiles([
                'q' => "'{$folderId}' in parents and trashed = false",
                'fields' => 'nextPageToken, files(id, name, mimeType, modifiedTime)',
                'pageSize' => 200,
                'pageToken' => $pageToken,
            ]);
            foreach ($resp->getFiles() as $f) {
                if ($f->getMimeType() === 'application/vnd.google-apps.folder') {
                    foreach ($this->collectFiles($drive, $f->getId(), $seenFolders) as $nested) {
                        $files[] = $nested;
                    }

                    continue;
                }
                $files[] = $f;
            }
            $pageToken = $resp->getNextPageToken();
        } while ($pageToken);

        return $files;
    }

    /**
     * Extract plain text for one Drive file. Empty string => unindexable.
     */
    public function extractText(string $fileId, string $mime, string $name): string
    {
        $drive = $this->drive();

        // Native Google Docs export straight to text.
        if ($mime === 'application/vnd.google-apps.document') {
            $content = $drive->files->export($fileId, 'text/plain', ['alt' => 'media'])->getBody()->getContents();

            return trim($content);
        }

        // Other Google-native types (sheets, slides) are skipped for v1.
        if (str_starts_with($mime, 'application/vnd.google-apps')) {
            return '';
        }

        $binary = $drive->files->get($fileId, ['alt' => 'media'])->getBody()->getContents();

        if ($mime === 'application/pdf' || str_ends_with(strtolower($name), '.pdf')) {
            return $this->extractPdf($binary);
        }

        if (str_contains($mime, 'wordprocessingml') || str_ends_with(strtolower($name), '.docx')) {
            return $this->extractDocx($binary);
        }

        if (str_starts_with($mime, 'text/')) {
            return trim($binary);
        }

        return '';
    }

    private function extractPdf(string $binary): string
    {
        try {
            $pdf = (new PdfParser)->parseContent($binary);
            $pages = [];
            foreach ($pdf->getPages() as $i => $page) {
                $t = trim($page->getText());
                if ($t !== '') {
                    $pages[] = 'p.'.($i + 1).":\n".$t;
                }
            }

            return trim(implode("\n\n", $pages));
        } catch (\Throwable) {
            return '';
        }
    }

    private function extractDocx(string $binary): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'rag_docx_').'.docx';
        file_put_contents($tmp, $binary);

        try {
            $phpWord = IOFactory::load($tmp);
            $text = '';
            foreach ($phpWord->getSections() as $section) {
                $text .= $this->elementsText($section->getElements());
            }

            return trim($text);
        } catch (\Throwable) {
            return '';
        } finally {
            @unlink($tmp);
        }
    }

    private function elementsText(array $elements): string
    {
        $out = '';
        foreach ($elements as $el) {
            if (method_exists($el, 'getText')) {
                $t = $el->getText();
                if (is_string($t)) {
                    $out .= $t.' ';
                }
            }
            if (method_exists($el, 'getElements')) {
                $out .= $this->elementsText($el->getElements());
            }
            if ($el instanceof TextBreak) {
                $out .= "\n";
            }
        }

        return $out;
    }
}
