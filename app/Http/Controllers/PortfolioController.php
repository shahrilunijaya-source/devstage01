<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Graph\TraceRelationship;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\Stage;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\Graph\TraceService;
use App\Services\Portfolio\PortfolioDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Portfolio surface (PRD §5). Reads are gated by the Policy Decision Point;
 * writes flow through it as a Policy Enforcement Point.
 */
class PortfolioController extends Controller
{
    public function __construct(private readonly PolicyDecisionPoint $pdp) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $allowedIds = $this->pdp->accessibleProjectIds($user, 'view');

        $tenants = Tenant::with(['projects' => fn ($q) => $q->whereIn('id', $allowedIds)->with('modules')])
            ->get()
            ->filter(fn (Tenant $t) => $t->projects->isNotEmpty())
            ->values();

        return view('portfolio.index', [
            'tenants' => $tenants,
            'canCreateProject' => $this->canCreateProject($request),
            'counts' => [
                'projects' => count($allowedIds),
                'objects' => EngObject::whereIn('project_id', $allowedIds)->count(),
                'traces' => TraceRelationship::whereIn('project_id', $allowedIds)->count(),
            ],
        ]);
    }

    public function dashboard(Request $request, PortfolioDashboardService $dashboard): View
    {
        $cards = $dashboard->forUser($request->user());

        return view('portfolio.dashboard', [
            'cards' => $cards,
            'summary' => $dashboard->summarize($cards),
            'columns' => $dashboard->lifecycleColumns(),
        ]);
    }

    /** Read-only project team: who holds an active ACL binding on this project. */
    public function team(Request $request, Project $project): View
    {
        abort_unless($this->pdp->can($request->user(), 'view', $project)->permitted, 403, 'Access denied by ACL.');

        $bindings = ScopeBinding::active()
            ->where(function ($q) use ($project): void {
                $q->where(fn ($w) => $w->where('scope_type', 'project')->where('scope_id', $project->id))
                    ->orWhere(fn ($w) => $w->where('scope_type', 'tenant')->where('tenant_id', $project->tenant_id));
            })
            ->with('user:id,name,email', 'role:id,key,name')
            ->get()
            ->sortBy('user.name')
            ->values();

        return view('portfolio.team', [
            'project' => $project,
            'bindings' => $bindings,
        ]);
    }

    /** Focused "what's blocked" view across the user's projects (CLAUDE.md). */
    public function blocked(Request $request, PortfolioDashboardService $dashboard): View
    {
        $blocked = $dashboard->forUser($request->user())
            ->filter(fn (array $c): bool => $c['health'] === 'blocked' || $c['blockedStages'] > 0)
            ->values();

        return view('portfolio.blocked', ['blocked' => $blocked]);
    }

    public function show(Request $request, Project $project, TraceService $trace): View
    {
        abort_unless($this->pdp->can($request->user(), 'view', $project)->permitted, 403, 'Access denied by ACL.');

        $project->load(['tenant', 'modules.stages.currentBaseline', 'modules.stages.sessions']);

        $firstEvidence = EngObject::where('project_id', $project->id)->where('type', 'evidence')->orderBy('id')->first();
        $chain = $firstEvidence
            ? collect([$firstEvidence, ...$trace->forwardTrace($firstEvidence)])
            : collect();

        return view('portfolio.show', [
            'project' => $project,
            'chain' => $chain,
            'objectCount' => EngObject::where('project_id', $project->id)->count(),
            'canEdit' => $this->pdp->can($request->user(), 'edit', $project)->permitted,
            'canBaseline' => $this->pdp->can($request->user(), 'baseline', $project)->permitted,
        ]);
    }

    public function createProject(Request $request): View
    {
        abort_unless($this->canCreateProject($request), 403);

        return view('portfolio.create-project', ['tenants' => Tenant::orderBy('name')->get()]);
    }

    public function storeProject(Request $request): RedirectResponse
    {
        abort_unless($this->canCreateProject($request), 403);

        $data = $request->validate([
            'tenant_id' => ['required', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'status' => ['required', 'in:active,archived'],
        ]);

        $project = Project::create($data);

        return redirect()->route('portfolio.show', $project)
            ->with('status', "Project {$project->name} created.");
    }

    public function storeModule(Request $request, Project $project): RedirectResponse
    {
        abort_unless($this->pdp->can($request->user(), 'edit', $project)->permitted, 403, 'Access denied by ACL.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
        ]);

        // Module creation auto-seeds the nine lifecycle stages.
        $project->modules()->create($data + ['status' => 'active']);

        return redirect()->route('portfolio.show', $project)
            ->with('status', "Module {$data['name']} added (stages seeded).");
    }

    public function storeSession(Request $request, Stage $stage): RedirectResponse
    {
        $project = $stage->project;
        abort_unless($this->pdp->can($request->user(), 'edit', $project)->permitted, 403, 'Access denied by ACL.');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'process' => ['nullable', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        Session::create($data + [
            'stage_id' => $stage->id,
            'module_id' => $stage->module_id,
            'project_id' => $stage->project_id,
            'status' => 'draft',
        ]);

        return redirect()->route('portfolio.show', $project)
            ->with('status', 'Session created.');
    }

    private function canCreateProject(Request $request): bool
    {
        // Creating a brand-new project has no existing scope to bind against;
        // restrict to platform admins/directors (system role).
        return $request->user()->isAdmin() || $request->user()->isDirector();
    }
}
