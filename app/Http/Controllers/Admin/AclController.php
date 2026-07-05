<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MetricsController;
use App\Models\Acl\AccessAudit;
use App\Models\Acl\Delegation;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\AccessControl\DelegationService;
use App\Services\AccessControl\Exceptions\DelegationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Administration of the Access Control module (PRD §6.4 ACL-12). Admin-only:
 * grant/revoke scope bindings and review the immutable access audit.
 */
class AclController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('admin.acl.index', [
            'bindings' => ScopeBinding::with('user', 'role', 'tenant')->latest()->get(),
            'delegations' => Delegation::with('delegator', 'delegate', 'role', 'tenant')->latest()->get(),
            'users' => User::orderBy('name')->get(),
            'roles' => Role::whereNull('tenant_id')->orderBy('key')->get(),
            'projects' => Project::with('tenant')->orderBy('name')->get(),
            'tenants' => Tenant::orderBy('name')->get(),
        ]);
    }

    public function grant(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            // Only global roles are grantable here (matches the console UI); a
            // tenant-scoped role id cannot be slipped in via a direct POST.
            'role_id' => ['required', Rule::exists('acl_roles', 'id')->whereNull('tenant_id')],
            'scope_type' => ['required', 'in:tenant,project'],
            'project_id' => ['nullable', 'required_if:scope_type,project', 'exists:projects,id'],
            'tenant_id' => ['nullable', 'required_if:scope_type,tenant', 'exists:tenants,id'],
        ]);

        if ($data['scope_type'] === 'project') {
            $project = Project::findOrFail($data['project_id']);
            $tenantId = $project->tenant_id;
            $scopeId = $project->id;
        } else {
            $tenantId = (int) $data['tenant_id'];
            $scopeId = null;
        }

        ScopeBinding::firstOrCreate(
            [
                'user_id' => $data['user_id'],
                'role_id' => $data['role_id'],
                'scope_type' => $data['scope_type'],
                'scope_id' => $scopeId,
            ],
            ['tenant_id' => $tenantId, 'granted_by' => $request->user()->id],
        );

        return redirect()->route('admin.acl.index')->with('status', 'Scope binding granted.');
    }

    public function revoke(Request $request, ScopeBinding $binding): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $binding->update(['revoked_at' => now()]);

        return redirect()->route('admin.acl.index')->with('status', 'Scope binding revoked.');
    }

    public function delegate(Request $request, DelegationService $service): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'delegator_id' => ['required', 'exists:users,id'],
            'delegate_id' => ['required', 'different:delegator_id', 'exists:users,id'],
            'role_id' => ['required', 'exists:acl_roles,id'],
            'scope_type' => ['required', 'in:tenant,project'],
            'project_id' => ['nullable', 'required_if:scope_type,project', 'exists:projects,id'],
            'tenant_id' => ['nullable', 'required_if:scope_type,tenant', 'exists:tenants,id'],
            'ends_at' => ['required', 'date', 'after:now'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['scope_type'] === 'project') {
            $project = Project::findOrFail($data['project_id']);
            $tenantId = (int) $project->tenant_id;
            $scopeId = (int) $project->id;
        } else {
            $tenantId = (int) $data['tenant_id'];
            $scopeId = null;
        }

        try {
            $service->delegate(
                delegator: User::findOrFail($data['delegator_id']),
                delegate: User::findOrFail($data['delegate_id']),
                roleId: (int) $data['role_id'],
                scopeType: $data['scope_type'],
                scopeId: $scopeId,
                tenantId: $tenantId,
                endsAt: Carbon::parse($data['ends_at']),
                reason: $data['reason'] ?? null,
            );
        } catch (DelegationException $e) {
            return redirect()->route('admin.acl.index')->withErrors(['delegation' => $e->getMessage()]);
        }

        return redirect()->route('admin.acl.index')->with('status', 'Delegation granted.');
    }

    public function revokeDelegation(Request $request, Delegation $delegation, DelegationService $service): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $service->revoke($delegation);

        return redirect()->route('admin.acl.index')->with('status', 'Delegation revoked.');
    }

    public function audit(Request $request): View
    {
        $this->authorizeAdmin($request);

        [$filters, $query] = $this->auditQuery($request);

        return view('admin.acl.audit', [
            'entries' => $query->with('user')->latest()->paginate(50)->withQueryString(),
            'filters' => $filters,
            'actions' => AccessAudit::query()->distinct()->orderBy('action')->pluck('action')->filter()->values(),
            'peps' => AccessAudit::query()->distinct()->orderBy('pep')->pluck('pep')->filter()->values(),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Export the filtered access audit as CSV (compliance evidence, PRD §6.4). */
    public function auditCsv(Request $request): StreamedResponse
    {
        $this->authorizeAdmin($request);

        [, $query] = $this->auditQuery($request);
        $rows = $query->with('user')->latest()->limit(10000)->get();

        return response()->streamDownload(function () use ($rows): void {
            $safe = MetricsController::csvSafe(...);
            $out = fopen('php://output', 'w');
            fputcsv($out, ['When', 'User', 'Decision', 'Action', 'Object type', 'Object id', 'Scope', 'Reason', 'IP', 'PEP']);
            foreach ($rows as $e) {
                fputcsv($out, array_map($safe, [
                    optional($e->created_at)->toDateTimeString(), $e->user?->name ?? '#'.$e->user_id,
                    $e->decision, $e->action, $e->object_type, $e->object_id, $e->scope_type,
                    $e->reason, $e->ip, $e->pep,
                ]));
            }
            fclose($out);
        }, 'access-audit.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Shared filter parsing + query builder for the audit list and its export.
     *
     * @return array{0: array<string, mixed>, 1: Builder}
     */
    private function auditQuery(Request $request): array
    {
        $filters = [
            'decision' => $request->query('decision'),
            'action' => $request->query('action'),
            'user_id' => $request->query('user_id'),
            'pep' => $request->query('pep'),
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ];

        $query = AccessAudit::query()
            ->when($filters['decision'], fn ($q, $v) => $q->where('decision', $v))
            ->when($filters['action'], fn ($q, $v) => $q->where('action', $v))
            ->when($filters['user_id'], fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['pep'], fn ($q, $v) => $q->where('pep', $v))
            ->when($filters['q'] !== '', function ($q) use ($filters) {
                $needle = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']);
                $q->where(fn ($w) => $w
                    ->where('reason', 'like', '%'.$needle.'%')
                    ->orWhere('object_type', 'like', '%'.$needle.'%'));
            })
            ->when($filters['from'], fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'], fn ($q, $v) => $q->whereDate('created_at', '<=', $v));

        return [$filters, $query];
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403, 'Administrators only.');
    }
}
