<?php

use App\Http\Controllers\Admin\AclController;
use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ChangeRequestController;
use App\Http\Controllers\DesignController;
use App\Http\Controllers\DiscussionController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\KnowledgeController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ObjectController;
use App\Http\Controllers\ObjectiveController;
use App\Http\Controllers\PortfolioChatController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\Project\CalendarController;
use App\Http\Controllers\ProjectKnowledgeController;
use App\Http\Controllers\PrototypeController;
use App\Http\Controllers\RefinementController;
use App\Http\Controllers\ReleaseController;
use App\Http\Controllers\RtmController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StageController;
use App\Http\Controllers\UrsbDashboardController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\WorkloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'portfolio.index' : 'login');
});

// Authentication (session-based).
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    // Coarse IP backstop; LoginController adds a finer per-email+IP lockout.
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// URSB application (authenticated).
Route::middleware('auth')->group(function () {
    Route::get('/portfolio', [PortfolioController::class, 'index'])->name('portfolio.index');
    Route::get('/portfolio/dashboard', [PortfolioController::class, 'dashboard'])->name('portfolio.dashboard');
    Route::get('/portfolio/blocked', [PortfolioController::class, 'blocked'])->name('portfolio.blocked');
    Route::get('/portfolio/projects/create', [PortfolioController::class, 'createProject'])->name('portfolio.projects.create');
    Route::post('/portfolio/projects', [PortfolioController::class, 'storeProject'])->name('portfolio.projects.store');
    Route::get('/portfolio/projects/{project}', [PortfolioController::class, 'show'])->name('portfolio.show');
    Route::get('/portfolio/projects/{project}/team', [PortfolioController::class, 'team'])->name('portfolio.team');

    // Project Objective Baseline (spec §6).
    Route::get('/portfolio/projects/{project}/objective', [ObjectiveController::class, 'show'])->name('objective.show');
    Route::get('/portfolio/projects/{project}/objective/edit', [ObjectiveController::class, 'edit'])->name('objective.edit');
    Route::post('/portfolio/projects/{project}/objective', [ObjectiveController::class, 'store'])->name('objective.store');
    Route::post('/portfolio/projects/{project}/objective/approve', [ObjectiveController::class, 'approve'])->name('objective.approve');
    Route::post('/portfolio/projects/{project}/modules', [PortfolioController::class, 'storeModule'])->name('portfolio.modules.store');
    Route::post('/portfolio/stages/{stage}/sessions', [PortfolioController::class, 'storeSession'])->name('portfolio.sessions.store');

    // Session Engine (PRD §9).
    Route::get('/portfolio/sessions/{session}', [SessionController::class, 'show'])->name('sessions.show');
    Route::post('/portfolio/sessions/{session}/evidence', [SessionController::class, 'addEvidence'])->name('sessions.evidence.store');
    Route::post('/portfolio/sessions/{session}/pre-analyze', [SessionController::class, 'preAnalyze'])->name('sessions.preanalyze');
    Route::post('/portfolio/sessions/{session}/firewall', [SessionController::class, 'firewall'])->name('sessions.firewall');
    Route::post('/portfolio/sessions/{session}/reject-firewall', [SessionController::class, 'rejectFirewall'])->name('sessions.reject-firewall');
    Route::post('/portfolio/sessions/{session}/start', [SessionController::class, 'start'])->name('sessions.start');
    Route::post('/portfolio/sessions/{session}/objects/{object}/capture', [SessionController::class, 'capture'])->name('sessions.capture');
    Route::post('/portfolio/sessions/{session}/scan-conflicts', [SessionController::class, 'scanConflicts'])->name('sessions.scan-conflicts');
    Route::post('/portfolio/sessions/{session}/consolidate', [SessionController::class, 'consolidate'])->name('sessions.consolidate');
    Route::post('/portfolio/sessions/{session}/approve', [SessionController::class, 'approve'])->name('sessions.approve');

    // Baselining + generated documents (PRD §9.7, §14).
    Route::get('/portfolio/stages/{stage}/gate', [StageController::class, 'gate'])->name('stages.gate');
    Route::post('/portfolio/stages/{stage}/status', [StageController::class, 'status'])->name('stages.status');
    Route::post('/portfolio/stages/{stage}/baseline', [StageController::class, 'baseline'])->name('stages.baseline');
    Route::post('/portfolio/baselines/{baseline}/reopen', [StageController::class, 'reopen'])->name('baselines.reopen');

    // Discussions (spec §12) — contextual threads at every level.
    Route::post('/portfolio/discussions', [DiscussionController::class, 'store'])->name('discussions.store');
    Route::post('/portfolio/discussions/{discussion}/comments', [DiscussionController::class, 'comment'])->name('discussions.comment');
    Route::post('/portfolio/discussions/{discussion}/resolve', [DiscussionController::class, 'resolve'])->name('discussions.resolve');
    Route::post('/portfolio/discussions/{discussion}/reopen', [DiscussionController::class, 'reopen'])->name('discussions.reopen');
    Route::post('/portfolio/discussions/{discussion}/comments/{comment}/convert', [DiscussionController::class, 'convert'])->name('discussions.convert');

    // AI Refinement Engine (spec §10) — throttled LLM-backed writes.
    Route::post('/portfolio/objects/{object}/refine', [RefinementController::class, 'suggest'])->middleware('throttle:30,1')->name('objects.refine');
    Route::post('/portfolio/suggestions/{suggestion}/decide', [RefinementController::class, 'decide'])->name('suggestions.decide');
    Route::post('/portfolio/changes/{change}/disposition/{object}', [ChangeRequestController::class, 'disposition'])->name('changes.disposition');
    Route::get('/portfolio/baselines/{baseline}', [DocumentController::class, 'baseline'])->name('baselines.show');
    Route::get('/portfolio/baselines/{baseline}/pdf', [DocumentController::class, 'baselinePdf'])->name('baselines.pdf');
    Route::get('/portfolio/baselines/{baseline}/deck', [DocumentController::class, 'deck'])->name('baselines.deck');
    Route::get('/portfolio/baselines/{baseline}/deck.pptx', [DocumentController::class, 'deckPptx'])->name('baselines.deck.pptx');

    // Change Management Engine (PRD §9.3.3).
    Route::get('/portfolio/projects/{project}/metrics', [MetricsController::class, 'show'])->name('metrics.show');
    Route::get('/portfolio/projects/{project}/coverage', [MetricsController::class, 'coverage'])->name('metrics.coverage');
    Route::get('/portfolio/projects/{project}/knowledge', [KnowledgeController::class, 'show'])->name('knowledge.show');
    Route::get('/portfolio/projects/{project}/insights', [ProjectKnowledgeController::class, 'index'])->name('project-knowledge.index');
    Route::post('/portfolio/projects/{project}/insights', [ProjectKnowledgeController::class, 'store'])->name('project-knowledge.store');
    Route::get('/portfolio/projects/{project}/activity', [MetricsController::class, 'activity'])->name('metrics.activity');
    Route::get('/portfolio/projects/{project}/decisions', [MetricsController::class, 'decisions'])->name('metrics.decisions');
    Route::get('/portfolio/projects/{project}/decisions.csv', [MetricsController::class, 'decisionsCsv'])->name('metrics.decisions.csv');
    Route::get('/portfolio/projects/{project}/risks', [MetricsController::class, 'risks'])->name('metrics.risks');
    Route::get('/portfolio/projects/{project}/risks.csv', [MetricsController::class, 'risksCsv'])->name('metrics.risks.csv');

    // Verification & Validation register (PRD §17).
    Route::get('/portfolio/projects/{project}/verification', [VerificationController::class, 'index'])->name('verification.index');
    Route::get('/portfolio/projects/{project}/verification.csv', [VerificationController::class, 'csv'])->name('verification.csv');
    Route::post('/portfolio/objects/{requirement}/test-cases', [VerificationController::class, 'storeTestCase'])->name('verification.test-cases.store');
    Route::post('/portfolio/test-cases/{case}/results', [VerificationController::class, 'recordResult'])->name('verification.results.store');
    Route::post('/portfolio/test-cases/{case}/defects', [VerificationController::class, 'raiseDefect'])->name('verification.defects.store');
    Route::post('/portfolio/defects/{defect}/resolve', [VerificationController::class, 'resolveDefect'])->name('verification.defects.resolve');

    // Design register (PRD §12 — SDS/SLD/DBD).
    Route::get('/portfolio/projects/{project}/design', [DesignController::class, 'index'])->name('design.index');
    Route::post('/portfolio/objects/{requirement}/design', [DesignController::class, 'store'])->name('design.store');

    // Prototype register (PRD §12 — PROTOTYPE stage).
    Route::get('/portfolio/projects/{project}/prototype', [PrototypeController::class, 'index'])->name('prototype.index');
    Route::post('/portfolio/objects/{requirement}/prototype', [PrototypeController::class, 'store'])->name('prototype.store');
    Route::post('/portfolio/prototype-elements/{element}/state', [PrototypeController::class, 'setState'])->name('prototype.state');

    // Project issue log (PRD §17).
    Route::get('/portfolio/projects/{project}/issues', [IssueController::class, 'index'])->name('issues.index');
    Route::post('/portfolio/projects/{project}/issues', [IssueController::class, 'store'])->name('issues.store');
    Route::post('/portfolio/issues/{issue}/resolve', [IssueController::class, 'resolve'])->name('issues.resolve');

    // Requirements Traceability Matrix (PRD §17).
    Route::get('/portfolio/projects/{project}/rtm', [RtmController::class, 'index'])->name('rtm.index');
    Route::get('/portfolio/projects/{project}/rtm.csv', [RtmController::class, 'csv'])->name('rtm.csv');
    Route::get('/portfolio/projects/{project}/rtm.pdf', [RtmController::class, 'pdf'])->name('rtm.pdf');

    Route::get('/portfolio/projects/{project}/changes', [ChangeRequestController::class, 'index'])->name('changes.index');
    Route::get('/portfolio/projects/{project}/objects', [ObjectController::class, 'index'])->name('objects.index');
    Route::get('/portfolio/objects/{object}', [ObjectController::class, 'show'])->name('objects.show');
    Route::get('/portfolio/objects/{object}/changes/create', [ChangeRequestController::class, 'create'])->name('changes.create');
    Route::post('/portfolio/objects/{object}/changes', [ChangeRequestController::class, 'store'])->name('changes.store');
    Route::get('/portfolio/changes/{change}', [ChangeRequestController::class, 'show'])->name('changes.show');
    Route::post('/portfolio/changes/{change}/approve', [ChangeRequestController::class, 'approve'])->name('changes.approve');
    Route::post('/portfolio/changes/{change}/reject', [ChangeRequestController::class, 'reject'])->name('changes.reject');
    Route::post('/portfolio/changes/{change}/apply', [ChangeRequestController::class, 'apply'])->name('changes.apply');

    // Admin surfaces — route-level can:admin gate (system_role) in addition to the
    // in-controller check, so a new admin action can never ship unguarded.
    Route::middleware('can:admin')->group(function () {
        // Access Control administration (PRD §6.4 ACL-12).
        Route::get('/admin/acl', [AclController::class, 'index'])->name('admin.acl.index');
        Route::post('/admin/acl/grant', [AclController::class, 'grant'])->name('admin.acl.grant');
        Route::post('/admin/acl/bindings/{binding}/revoke', [AclController::class, 'revoke'])->name('admin.acl.revoke');
        Route::post('/admin/acl/delegate', [AclController::class, 'delegate'])->name('admin.acl.delegate');
        Route::post('/admin/acl/delegations/{delegation}/revoke', [AclController::class, 'revokeDelegation'])->name('admin.acl.delegation.revoke');
        Route::get('/admin/acl/audit', [AclController::class, 'audit'])->name('admin.acl.audit');
        Route::get('/admin/acl/audit.csv', [AclController::class, 'auditCsv'])->name('admin.acl.audit.csv');

        // Platform settings (API keys + feature flags).
        Route::get('/admin/settings', [SettingsController::class, 'index'])->name('admin.settings.index');
        Route::post('/admin/settings', [SettingsController::class, 'update'])->name('admin.settings.update');
    });

    // How to Use — static onboarding guide with workflow diagram.
    Route::get('/guide', [GuideController::class, 'index'])->name('guide.index');

    // Cross-project action inbox + object search.
    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox');
    Route::get('/search', [SearchController::class, 'index'])->name('search');

    // Phase-1 graph verification view.
    Route::get('/ursb', [UrsbDashboardController::class, 'index'])->name('ursb.dashboard');
});

// Authenticated routes
Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/dashboard', function () {
        return redirect()->route('portfolio.dashboard');
    })->name('dashboard');

    // Portfolio-wide AI chat — throttle the LLM-backed POST (cost + abuse guard).
    Route::get('/portfolio/chat', [PortfolioChatController::class, 'index'])->name('portfolio.chat');
    Route::post('/portfolio/chat/ask', [PortfolioChatController::class, 'ask'])->middleware('throttle:30,1')->name('portfolio.chat.ask');

    // Module 6: Feedback
    Route::prefix('feedback')->group(function () {
        Route::get('/', [FeedbackController::class, 'index'])->name('feedback.index');
        Route::post('/', [FeedbackController::class, 'store'])->name('feedback.store');
        // Keep the attachment route above /{feedback} so "attachments" isn't bound as an id.
        Route::get('/attachments/{attachment}', [FeedbackController::class, 'download'])->name('feedback.attachments.download');
        Route::get('/{feedback}', [FeedbackController::class, 'show'])->name('feedback.show');
    });

    // What's New — release feed. Rows auto-populate from commits (releases:sync).
    Route::get('/updates', [ReleaseController::class, 'index'])->name('releases.index');
    Route::post('/releases/seen', [ReleaseController::class, 'seen'])->name('releases.seen');

    // Module 8: Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
        Route::patch('/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('/mark-all-read', [NotificationController::class, 'readAll'])->name('notifications.mark-all-read');
    });

    // Module 10: Workload
    Route::get('/workload', [WorkloadController::class, 'index'])->name('workload.index');
    Route::get('/workload/{user}', [WorkloadController::class, 'show'])->name('workload.show');

    // Module 12: Calendar
    Route::prefix('calendar')->group(function () {
        Route::get('/', [CalendarController::class, 'index'])->name('calendar.index');
        Route::get('/events', [CalendarController::class, 'events'])->name('calendar.events');
    });

    // Admin: Feedback Triage (admins + directors — enforced per-action by FeedbackItemPolicy).
    Route::prefix('admin/feedback')->group(function () {
        Route::get('/', [AdminFeedbackController::class, 'index'])->name('admin.feedback.index');
        Route::get('/{feedback}', [AdminFeedbackController::class, 'show'])->name('admin.feedback.show');
        Route::put('/{feedback}', [AdminFeedbackController::class, 'update'])->name('admin.feedback.update');
        Route::delete('/{feedback}', [AdminFeedbackController::class, 'destroy'])->name('admin.feedback.destroy');
    });
});
