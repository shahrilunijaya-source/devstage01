<?php

namespace App\Providers;

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
        //
    }
}
