<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class ReleasesGenerate extends Command
{
    protected $signature = 'releases:generate
        {--from= : Commit/tag to diff from (defaults to the last recorded entry, then the last tag)}';

    protected $description = 'Generate release notes from conventional commits into database/changelog.json (run locally — needs git)';

    /** Conventional-commit prefix → changelog bucket. Others are skipped. */
    private const BUCKETS = [
        'feat' => 'new',
        'fix' => 'fixed',
        'perf' => 'improved',
        'refactor' => 'improved',
    ];

    public function handle(): int
    {
        if (! $this->gitOk()) {
            $this->error('git not available or not a git repository. Run this on a developer machine.');

            return self::FAILURE;
        }

        $head = $this->git('rev-parse HEAD');
        if ($head === null) {
            $this->error('Could not resolve HEAD.');

            return self::FAILURE;
        }

        $path = database_path('changelog.json');
        $existing = $this->readChangelog($path);

        // Where to diff from: explicit option → last recorded entry → last tag → repo root.
        $baseTag = $this->git('describe --tags --abbrev=0');
        $from = $this->option('from')
            ?: ($existing[0]['sha'] ?? null)
            ?: $baseTag;

        if (($existing[0]['sha'] ?? null) === $head) {
            $this->info('changelog.json already current at HEAD — nothing to do.');

            return self::SUCCESS;
        }

        $range = $from ? "{$from}..HEAD" : 'HEAD';
        $log = $this->git("log --no-merges --pretty=format:%H%x1f%s {$range}");

        $buckets = ['new' => [], 'fixed' => [], 'improved' => []];

        foreach (array_filter(explode("\n", (string) $log)) as $line) {
            [$sha, $subject] = array_pad(explode("\x1f", $line, 2), 2, '');
            $parsed = $this->parseSubject($subject);
            if ($parsed === null) {
                continue;
            }
            [$bucket, $text] = $parsed;
            $buckets[$bucket][] = $text;
        }

        if (! array_filter($buckets)) {
            $this->info('No user-facing commits since '.($from ?: 'start').' — no changelog entry written.');

            return self::SUCCESS;
        }

        // Version: marketing tag + build count since that tag (e.g. 1.0.0-122).
        $base = $baseTag ? ltrim($baseTag, 'v') : ltrim((string) config('app.version', '1.0.0'), 'v');
        $buildCount = (int) ($this->git("rev-list --count {$baseTag}..HEAD") ?? 0);
        $version = $buildCount > 0 ? "{$base}-{$buildCount}" : $base;
        $title = $buildCount > 0 ? "v{$base} · build {$buildCount}" : "v{$base}";

        $date = $this->git('log -1 --format=%cI HEAD') ?? date('c');

        $entry = [
            'version' => $version,
            'title' => $title,
            'date' => $date,
            'sha' => $head,
            'notes' => $buckets,
        ];

        // Replace any same-version entry (re-run safety), then prepend newest-first.
        $existing = array_values(array_filter($existing, fn ($e) => ($e['version'] ?? null) !== $version));
        array_unshift($existing, $entry);

        File::put($path, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

        $this->info(sprintf(
            'Wrote %s → new:%d fixed:%d improved:%d',
            $version,
            count($buckets['new']),
            count($buckets['fixed']),
            count($buckets['improved'])
        ));

        return self::SUCCESS;
    }

    /** Parse "feat(scope): message" → [bucket, "Message"]. Null if not a tracked type. */
    private function parseSubject(string $subject): ?array
    {
        if (! preg_match('/^(\w+)(\([^)]*\))?!?:\s*(.+)$/', trim($subject), $m)) {
            return null;
        }

        $type = strtolower($m[1]);
        if (! isset(self::BUCKETS[$type])) {
            return null;
        }

        $text = trim($m[3]);
        $text = $text === '' ? $text : ucfirst($text);

        return [self::BUCKETS[$type], $text];
    }

    private function readChangelog(string $path): array
    {
        if (! File::exists($path)) {
            return [];
        }

        $data = json_decode(File::get($path), true);

        return is_array($data) ? $data : [];
    }

    private function gitOk(): bool
    {
        return Process::path(base_path())->run('git rev-parse --is-inside-work-tree')->successful();
    }

    private function git(string $args): ?string
    {
        $result = Process::path(base_path())->run('git '.$args);

        return $result->successful() ? trim($result->output()) : null;
    }
}
