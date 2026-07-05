<?php

namespace App\Providers;

use App\Models\Release;
use App\Services\InboxService;
use App\Services\NotificationService;
use App\Services\Rag\AnthropicClient;
use App\Services\Rag\RagService;
use App\Services\Rag\Tools\ObjectSearchTool;
use App\Services\Rag\Tools\ProjectStatusTool;
use App\Services\Rag\Tools\ToolRegistry;
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
        // RAG System. ToolRegistry takes a plain array of ChatTool implementations,
        // which the container cannot autowire — bind it explicitly or RagService
        // (and both chat endpoints) fail to resolve.
        $this->app->singleton(ToolRegistry::class, fn ($app): ToolRegistry => new ToolRegistry([
            $app->make(ProjectStatusTool::class),
            $app->make(ObjectSearchTool::class),
        ]));
        $this->app->singleton(RagService::class);
        $this->app->singleton(AnthropicClient::class);
        $this->app->singleton(VoyageClient::class);

        // Other services
        $this->app->singleton(NotificationService::class);
        $this->app->singleton(WorkloadService::class);

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
        // sidebar unseen badge. The hub modal needs the live model (methods +
        // notes), so we memoize the query for the duration of the request rather
        // than caching the serialized Eloquent model across requests — a stale
        // cross-request cache deserializes to __PHP_Incomplete_Class and throws
        // on property access. One indexed single-row lookup per request is cheap.
        // Wrapped so a missing releases table (early migrations) never breaks render.
        $latestRelease = null;
        $latestResolved = false;
        View::composer(['layouts.ursb', 'layouts.app', 'partials.sidebar'], function ($view) use (&$latestRelease, &$latestResolved): void {
            if (! $latestResolved) {
                try {
                    $latestRelease = Release::current();
                } catch (\Throwable) {
                    $latestRelease = null;
                }
                $latestResolved = true;
            }
            $latest = $latestRelease;

            $user = auth()->user();
            $unseen = $latest !== null
                && $user !== null
                && $user->last_seen_version !== $latest->version;

            $view->with('latestRelease', $latest);
            $view->with('releaseUnseen', $unseen);
        });
    }
}
