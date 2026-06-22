<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // RAG System
        $this->app->singleton(\App\Services\Rag\RagService::class);
        $this->app->singleton(\App\Services\Rag\AnthropicClient::class);
        $this->app->singleton(\App\Services\Rag\VoyageClient::class);

        // Other services
        $this->app->singleton(\App\Services\NotificationService::class);
        $this->app->singleton(\App\Services\WorkloadService::class);
        $this->app->singleton(\App\Services\MonthlyReportService::class);
        $this->app->singleton(\App\Services\ProjectInsightService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
