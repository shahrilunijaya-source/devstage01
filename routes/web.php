<?php

use App\Http\Controllers\Admin\AclController;
use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ChangeRequestController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\KnowledgeController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ObjectController;
use App\Http\Controllers\PortfolioChatController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\Project\CalendarController;
use App\Http\Controllers\Project\ChatController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StageController;
use App\Http\Controllers\UrsbDashboardController;
use App\Http\Controllers\WorkloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'portfolio.index' : 'login');
});

// Authentication (session-based).
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// URSB application (authenticated).
Route::middleware('auth')->group(function () {
    Route::get('/portfolio', [PortfolioController::class, 'index'])->name('portfolio.index');
    Route::get('/portfolio/dashboard', [PortfolioController::class, 'dashboard'])->name('portfolio.dashboard');
    Route::get('/portfolio/projects/create', [PortfolioController::class, 'createProject'])->name('portfolio.projects.create');
    Route::post('/portfolio/projects', [PortfolioController::class, 'storeProject'])->name('portfolio.projects.store');
    Route::get('/portfolio/projects/{project}', [PortfolioController::class, 'show'])->name('portfolio.show');
    Route::post('/portfolio/projects/{project}/modules', [PortfolioController::class, 'storeModule'])->name('portfolio.modules.store');
    Route::post('/portfolio/stages/{stage}/sessions', [PortfolioController::class, 'storeSession'])->name('portfolio.sessions.store');

    // Session Engine (PRD §9).
    Route::get('/portfolio/sessions/{session}', [SessionController::class, 'show'])->name('sessions.show');
    Route::post('/portfolio/sessions/{session}/evidence', [SessionController::class, 'addEvidence'])->name('sessions.evidence.store');
    Route::post('/portfolio/sessions/{session}/pre-analyze', [SessionController::class, 'preAnalyze'])->name('sessions.preanalyze');
    Route::post('/portfolio/sessions/{session}/firewall', [SessionController::class, 'firewall'])->name('sessions.firewall');
    Route::post('/portfolio/sessions/{session}/start', [SessionController::class, 'start'])->name('sessions.start');
    Route::post('/portfolio/sessions/{session}/objects/{object}/capture', [SessionController::class, 'capture'])->name('sessions.capture');
    Route::post('/portfolio/sessions/{session}/scan-conflicts', [SessionController::class, 'scanConflicts'])->name('sessions.scan-conflicts');
    Route::post('/portfolio/sessions/{session}/consolidate', [SessionController::class, 'consolidate'])->name('sessions.consolidate');
    Route::post('/portfolio/sessions/{session}/approve', [SessionController::class, 'approve'])->name('sessions.approve');

    // Baselining + generated documents (PRD §9.7, §14).
    Route::post('/portfolio/stages/{stage}/baseline', [StageController::class, 'baseline'])->name('stages.baseline');
    Route::get('/portfolio/baselines/{baseline}', [DocumentController::class, 'baseline'])->name('baselines.show');
    Route::get('/portfolio/baselines/{baseline}/pdf', [DocumentController::class, 'baselinePdf'])->name('baselines.pdf');
    Route::get('/portfolio/baselines/{baseline}/deck', [DocumentController::class, 'deck'])->name('baselines.deck');

    // Change Management Engine (PRD §9.3.3).
    Route::get('/portfolio/projects/{project}/metrics', [MetricsController::class, 'show'])->name('metrics.show');
    Route::get('/portfolio/projects/{project}/coverage', [MetricsController::class, 'coverage'])->name('metrics.coverage');
    Route::get('/portfolio/projects/{project}/knowledge', [KnowledgeController::class, 'show'])->name('knowledge.show');
    Route::get('/portfolio/projects/{project}/activity', [MetricsController::class, 'activity'])->name('metrics.activity');

    Route::get('/portfolio/projects/{project}/changes', [ChangeRequestController::class, 'index'])->name('changes.index');
    Route::get('/portfolio/projects/{project}/objects', [ObjectController::class, 'index'])->name('objects.index');
    Route::get('/portfolio/objects/{object}', [ObjectController::class, 'show'])->name('objects.show');
    Route::get('/portfolio/objects/{object}/changes/create', [ChangeRequestController::class, 'create'])->name('changes.create');
    Route::post('/portfolio/objects/{object}/changes', [ChangeRequestController::class, 'store'])->name('changes.store');
    Route::get('/portfolio/changes/{change}', [ChangeRequestController::class, 'show'])->name('changes.show');
    Route::post('/portfolio/changes/{change}/approve', [ChangeRequestController::class, 'approve'])->name('changes.approve');
    Route::post('/portfolio/changes/{change}/reject', [ChangeRequestController::class, 'reject'])->name('changes.reject');
    Route::post('/portfolio/changes/{change}/apply', [ChangeRequestController::class, 'apply'])->name('changes.apply');

    // Access Control administration (PRD §6.4 ACL-12).
    Route::get('/admin/acl', [AclController::class, 'index'])->name('admin.acl.index');
    Route::post('/admin/acl/grant', [AclController::class, 'grant'])->name('admin.acl.grant');
    Route::post('/admin/acl/bindings/{binding}/revoke', [AclController::class, 'revoke'])->name('admin.acl.revoke');
    Route::post('/admin/acl/delegate', [AclController::class, 'delegate'])->name('admin.acl.delegate');
    Route::post('/admin/acl/delegations/{delegation}/revoke', [AclController::class, 'revokeDelegation'])->name('admin.acl.delegation.revoke');
    Route::get('/admin/acl/audit', [AclController::class, 'audit'])->name('admin.acl.audit');

    // Platform settings (API keys + feature flags).
    Route::get('/admin/settings', [SettingsController::class, 'index'])->name('admin.settings.index');
    Route::post('/admin/settings', [SettingsController::class, 'update'])->name('admin.settings.update');

    // Phase-1 graph verification view.
    Route::get('/ursb', [UrsbDashboardController::class, 'index'])->name('ursb.dashboard');
});

// Authenticated routes
Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/dashboard', function () {
        return redirect()->route('portfolio.dashboard');
    })->name('dashboard');

    // Module 1: AI/RAG Chat
    Route::prefix('chat')->group(function () {
        Route::get('/', [ChatController::class, 'index'])->name('chat.index');
        Route::post('/message', [ChatController::class, 'send'])->name('chat.send');
    });

    // Portfolio-wide AI chat
    Route::get('/portfolio/chat', [PortfolioChatController::class, 'index'])->name('portfolio.chat');
    Route::post('/portfolio/chat/ask', [PortfolioChatController::class, 'ask'])->name('portfolio.chat.ask');

    // Module 6: Feedback
    Route::prefix('feedback')->group(function () {
        Route::get('/', [FeedbackController::class, 'index'])->name('feedback.index');
        Route::post('/', [FeedbackController::class, 'store'])->name('feedback.store');
        Route::get('/{feedback}', [FeedbackController::class, 'show'])->name('feedback.show');
    });

    // Module 8: Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
        Route::patch('/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
    });

    // Module 10: Workload
    Route::get('/workload', [WorkloadController::class, 'index'])->name('workload.index');
    Route::get('/workload/{user}', [WorkloadController::class, 'show'])->name('workload.show');

    // Module 12: Calendar
    Route::prefix('calendar')->group(function () {
        Route::get('/', [CalendarController::class, 'index'])->name('calendar.index');
        Route::get('/events', [CalendarController::class, 'events'])->name('calendar.events');
    });

    // Admin: Feedback Triage
    Route::middleware('can:admin')->prefix('admin/feedback')->group(function () {
        Route::get('/', [AdminFeedbackController::class, 'index'])->name('admin.feedback.index');
        Route::patch('/{feedback}/triage', [AdminFeedbackController::class, 'triage'])->name('admin.feedback.triage');
        Route::post('/{feedback}/comment', [AdminFeedbackController::class, 'comment'])->name('admin.feedback.comment');
    });
});
