<?php

namespace App\Console\Commands;

use App\Models\Release;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ReleasesSync extends Command
{
    protected $signature = 'releases:sync';

    protected $description = 'Upsert release notes from database/changelog.json or git tags into the releases table (auto-run on deploy)';

    public function handle(): int
    {
        $path = database_path('changelog.json');

        // Try changelog.json first (preferred)
        if (File::exists($path)) {
            $entries = json_decode(File::get($path), true);

            if (is_array($entries) && $entries !== []) {
                return $this->syncFromChangelog($entries);
            }
        }

        // Fallback: read git tags directly
        if ($this->isGitAvailable()) {
            $this->info('No changelog.json found — syncing directly from git tags...');

            return $this->syncFromGitTags();
        }

        $this->warn('No changelog.json and git not available — nothing to sync.');

        return self::SUCCESS;
    }

    protected function syncFromChangelog(array $entries): int
    {
        $synced = 0;

        foreach ($entries as $entry) {
            if (empty($entry['version']) || empty($entry['notes'])) {
                continue;
            }

            Release::updateOrCreate(
                ['version' => $entry['version']],
                [
                    'title' => $entry['title'] ?? null,
                    'notes' => $entry['notes'],
                    'git_sha' => $entry['sha'] ?? null,
                    'released_at' => isset($entry['date']) ? Carbon::parse($entry['date']) : now(),
                ]
            );

            $synced++;
        }

        $this->info("Synced {$synced} release(s) from changelog.json.");

        return self::SUCCESS;
    }

    protected function syncFromGitTags(): int
    {
        if (! function_exists('exec')) {
            $this->warn('exec() disabled — cannot read git tags directly.');

            return self::FAILURE;
        }

        try {
            // Get all tags matching version pattern (v1.0.0, v1.2.0, etc)
            exec('git tag -l "v*.*.*" --sort=-version:refname', $tags);

            if (empty($tags)) {
                $this->warn('No version tags found.');

                return self::SUCCESS;
            }

            $synced = 0;

            foreach ($tags as $tag) {
                // Get tag details - use for-each-ref to handle annotated tags properly
                $output = [];
                exec("git for-each-ref refs/tags/{$tag} --format=\"%(creatordate:iso-strict)|%(*objectname)|%(contents:subject)\"", $output);

                if (empty($output)) {
                    continue;
                }

                $parts = explode('|', $output[0], 3);

                if (count($parts) < 3) {
                    continue;
                }

                [$date, $sha, $message] = $parts;

                // Parse tag message for structured notes
                $notes = $this->parseTagMessage($message);

                // Strip 'v' prefix from version
                $version = ltrim($tag, 'v');

                Release::updateOrCreate(
                    ['version' => $version],
                    [
                        'title' => $tag,
                        'notes' => $notes,
                        'git_sha' => $sha,
                        'released_at' => Carbon::parse($date),
                    ]
                );

                $synced++;
            }

            $this->info("Synced {$synced} release(s) from git tags.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to read git tags: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function parseTagMessage(string $message): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $message)));

        $notes = [
            'new' => [],
            'improved' => [],
            'fixed' => [],
        ];

        $currentSection = 'new';

        foreach ($lines as $line) {
            // Section headers
            if (stripos($line, 'new:') === 0 || stripos($line, 'features:') === 0) {
                $currentSection = 'new';

                continue;
            }

            if (stripos($line, 'improved:') === 0 || stripos($line, 'enhancements:') === 0) {
                $currentSection = 'improved';

                continue;
            }

            if (stripos($line, 'fixed:') === 0 || stripos($line, 'fixes:') === 0) {
                $currentSection = 'fixed';

                continue;
            }

            // Bullet points
            if (str_starts_with($line, '-') || str_starts_with($line, '*')) {
                $notes[$currentSection][] = ltrim($line, '- *');
            }
        }

        return $notes;
    }

    protected function isGitAvailable(): bool
    {
        // Check if exec() is available (disabled on many shared hosts)
        if (! function_exists('exec')) {
            return false;
        }

        try {
            exec('git --version 2>&1', $output, $returnCode);

            return $returnCode === 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
