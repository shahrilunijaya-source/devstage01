<?php

namespace App\Providers;

use App\Models\Release;
use App\Services\InboxService;
use App\Services\MonthlyReportService;
use App\Services\NotificationService;
use App\Services\ProjectInsightService;
use App\Services\Rag\AnthropicClient;
use App\Services\Rag\RagService;
use App\Services\Rag\VoyageClient;
use App\Services\Session\Analysis\DeterministicEvidenceAnalyst;
use App\Services\Session\Analysis\EvidenceAnalyst;
use App\Services\Session\Analysis\LlmEvidenceAnalyst;
use App\Services\WorkloadService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // RAG System
        $this->app->singleton(RagService::class);
        $this->app->singleton(AnthropicClient::class);
        $this->app->singleton(VoyageClient::class);

        // Other services
        $this->app->singleton(NotificationService::class);
        $this->app->singleton(WorkloadService::class);
        $this->app->singleton(MonthlyReportService::class);
        $this->app->singleton(ProjectInsightService::class);

        // Session pre-analysis: use the LLM analyst when RAG keys are configured,
        // otherwise the deterministic offline analyst (PRD §9.2). enabled() can throw
        // if the RAG settings store is absent — treat any failure as "disabled".
        $this->app->bind(EvidenceAnalyst::class, function ($app) {
            try {
                $useLlm = RagService::enabled();
            } catch (\Throwable) {
                $useLlm = false;
            }

            return $useLlm
                ? $app->make(LlmEvidenceAnalyst::class)
                : $app->make(DeterministicEvidenceAnalyst::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Sidebar shows the count of items waiting on the current user. Cached
        // briefly (per user) so the full inbox computation doesn't run on every
        // page render; the inbox page itself always loads live.
        View::composer('partials.sidebar', function ($view): void {
            $user = auth()->user();
            $count = $user
                ? Cache::remember("inbox_count:{$user->id}", 60, fn (): int => app(InboxService::class)->forUser($user)['total'])
                : 0;
            $view->with('inboxCount', $count);
        });

        // "What's New" feed: the newest release drives the hub modal and the
        // sidebar unseen badge. The release row is global, so cache the lookup
        // briefly; the unseen check is a cheap per-user string compare. Wrapped
        // so a missing releases table (early migrations) never breaks rendering.
        View::composer(['layouts.ursb', 'layouts.app', 'partials.sidebar'], function ($view): void {
            $latest = Cache::remember('release_current', 300, function (): ?Release {
                try {
                    return Release::current();
                } catch (\Throwable) {
                    return null;
                }
            });

            $user = auth()->user();
            $unseen = $latest !== null
                && $user !== null
                && $user->last_seen_version !== $latest->version;

            $view->with('latestRelease', $latest);
            $view->with('releaseUnseen', $unseen);
        });
    }
}
