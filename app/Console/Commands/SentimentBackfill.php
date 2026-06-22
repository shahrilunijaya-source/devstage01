<?php

namespace App\Console\Commands;

use App\Jobs\ScoreSentimentJob;
use App\Models\Project;
use App\Services\Rag\RagService;
use App\Services\Sentiment\SentimentScorer;
use Illuminate\Console\Command;

class SentimentBackfill extends Command
{
    protected $signature = 'sentiment:backfill
        {--project= : Limit to a single project (id or code)}';

    protected $description = 'Queue morale scoring for all existing comments, weekly updates and issues (run once after enabling AI Chat)';

    public function handle(): int
    {
        if (! RagService::enabled()) {
            $this->error('AI Chat is not enabled. Set the keys + tick Enable in admin Settings first.');

            return self::FAILURE;
        }

        $projects = Project::query()
            ->when($this->option('project'), fn ($q, $p) => $q->where(fn ($w) => $w->where('id', $p)->orWhere('code', $p)))
            ->get();

        if ($projects->isEmpty()) {
            $this->warn('No matching projects.');

            return self::SUCCESS;
        }

        $queued = 0;
        foreach ($projects as $project) {
            $this->line("• {$project->code} — {$project->name}");

            foreach (SentimentScorer::SOURCE_MAP as $sourceType => $modelClass) {
                $ids = $modelClass::query()->where('project_id', $project->id)->pluck('id');
                foreach ($ids as $id) {
                    ScoreSentimentJob::dispatch($project->id, $sourceType, (int) $id);
                    $queued++;
                }
                if ($ids->isNotEmpty()) {
                    $this->line("    {$sourceType}: {$ids->count()}");
                }
            }
        }

        $this->newLine();
        $this->info("Queued {$queued} scoring job(s).");
        $this->comment('Scoring runs on the queue — make sure a worker is running (composer dev / queue:work).');

        return self::SUCCESS;
    }
}
