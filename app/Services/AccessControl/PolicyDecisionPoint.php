<?php

declare(strict_types=1);

namespace App\Services\AccessControl;

use App\Models\Acl\AccessAudit;
use App\Models\Acl\ScopeBinding;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The single authority for every access decision (PRD §6.2). Default DENY:
 * access must be explicitly granted by a role bound to a covering scope.
 */
class PolicyDecisionPoint
{
    /** Actions a director may perform read-only without an explicit binding. */
    private const READ_ACTIONS = ['view', 'retrieve', 'export'];

    /**
     * Request-scoped caches (the PDP is a singleton). List views call can()
     * once per row with the same user; without this, each row re-queries the
     * user's bindings, role permissions, and rule tables. Invalidated by
     * ScopeBinding writes (see ScopeBinding::booted) so a mid-request grant or
     * revoke is honoured immediately.
     *
     * @var array<string, list<ScopeBinding>>
     */
    private array $bindingCache = [];

    /** @var array<string, list<string>> */
    private array $effectCache = [];

    /** @var Collection<int, object>|null */
    private ?Collection $objectRuleCache = null;

    /** @var Collection<int, object>|null */
    private ?Collection $fieldRuleCache = null;

    public function __construct(private readonly ScopeResolver $resolver) {}

    /** Drop the request-scoped caches (called when ACL bindings change). */
    public function flushScopeCache(): void
    {
        $this->bindingCache = [];
        $this->effectCache = [];
        $this->objectRuleCache = null;
        $this->fieldRuleCache = null;
    }

    public function can(User $user, string $action, mixed $object = null, ?string $field = null, string $pep = 'gate'): Decision
    {
        $ref = $this->resolver->resolve($object);

        if ($user->isAdmin()) {
            return $this->audit(Decision::permit('admin'), $user, $action, $ref, $pep, $field);
        }

        if ($user->isDirector() && in_array($action, self::READ_ACTIONS, true)) {
            return $this->audit(Decision::permit('director-read'), $user, $action, $ref, $pep, $field);
        }

        // No resolvable scope — abstain so existing Laravel policies decide.
        if ($ref === null) {
            return Decision::abstain();
        }

        $bindings = $this->coveringBindings($user, $ref);

        if ($bindings === []) {
            return $this->audit(Decision::deny('no active binding for scope'), $user, $action, $ref, $pep, $field);
        }

        $roleIds = array_values(array_unique(array_map(fn (ScopeBinding $b): int => (int) $b->role_id, $bindings)));
        sort($roleIds);

        $effectKey = implode(',', $roleIds).'|'.$action.'|'.$ref->objectType;
        $effects = $this->effectCache[$effectKey] ??= DB::table('acl_role_permission')
            ->join('acl_permissions', 'acl_role_permission.permission_id', '=', 'acl_permissions.id')
            ->whereIn('acl_role_permission.role_id', $roleIds)
            ->where('acl_permissions.action', $action)
            ->whereIn('acl_permissions.object_type', [$ref->objectType, '*'])
            ->pluck('acl_role_permission.effect')
            ->all();

        if ($effects === []) {
            return $this->audit(Decision::deny('no role grants this action on scope'), $user, $action, $ref, $pep, $field);
        }

        if (in_array('deny', $effects, true)) {
            return $this->audit(Decision::deny('explicit deny'), $user, $action, $ref, $pep, $field);
        }

        // Object-rule layer: attribute-based deny (classification/status), PRD §6.3.
        if ($this->objectRuleDenies($ref, $action)) {
            return $this->audit(Decision::deny('denied by object rule'), $user, $action, $ref, $pep, $field);
        }

        return $this->audit(Decision::permit('granted by role'), $user, $action, $ref, $pep, $field);
    }

    /**
     * Fields to redact when serializing an object to this user (PRD §6.3 ACL-05).
     * Admins have full clearance; everyone else is subject to field rules.
     *
     * @param  array<int, string>  $fields
     * @return array<int, string>
     */
    public function filterFields(User $user, mixed $object, array $fields): array
    {
        if ($user->isAdmin()) {
            return [];
        }

        $ref = $this->resolver->resolve($object);

        if ($ref === null) {
            return [];
        }

        $rules = $this->fieldRuleCache ??= DB::table('acl_field_rules')->get();

        return $rules
            ->filter(fn ($r): bool => in_array($r->object_type, [$ref->objectType, '*'], true)
                && in_array($r->field, $fields, true)
                && $r->effect === 'redact'
                && ($r->classification === null || $r->classification === $ref->classification))
            ->pluck('field')
            ->unique()
            ->values()
            ->all();
    }

    private function objectRuleDenies(ScopeRef $ref, string $action): bool
    {
        // The rules table is tiny and request-stable — load once, match in PHP.
        $rules = $this->objectRuleCache ??= DB::table('acl_object_rules')->get();

        return $rules->contains(fn ($r): bool => in_array($r->object_type, [$ref->objectType, '*'], true)
            && $r->action === $action
            && $r->effect === 'deny'
            && ($r->match_classification === null || $r->match_classification === $ref->classification)
            && ($r->match_status === null || $r->match_status === $ref->status));
    }

    /** Throwing variant for Policy Enforcement Points. */
    public function authorize(User $user, string $action, mixed $object = null, ?string $field = null, string $pep = 'gate'): void
    {
        $decision = $this->can($user, $action, $object, $field, $pep);

        if (! $decision->permitted) {
            throw new Exceptions\AccessDeniedException($decision->reason);
        }
    }

    /**
     * Project ids the user may act on (PRD §6.4 — feeds AI/RAG scope).
     *
     * @return array<int, int>
     */
    public function accessibleProjectIds(User $user, string $action = 'retrieve'): array
    {
        if ($user->isAdmin() || $user->isDirector()) {
            return DB::table('projects')->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        $grantRoleIds = DB::table('acl_role_permission')
            ->join('acl_permissions', 'acl_role_permission.permission_id', '=', 'acl_permissions.id')
            ->where('acl_permissions.action', $action)
            ->whereIn('acl_permissions.object_type', ['project', '*'])
            ->where('acl_role_permission.effect', 'permit')
            ->pluck('acl_role_permission.role_id')
            ->all();

        if ($grantRoleIds === []) {
            return [];
        }

        $bindings = ScopeBinding::active()
            ->where('user_id', $user->id)
            ->whereIn('role_id', $grantRoleIds)
            ->get();

        $projectIds = [];

        foreach ($bindings as $binding) {
            match ($binding->scope_type) {
                'project' => $projectIds[] = (int) $binding->scope_id,
                'tenant' => array_push(
                    $projectIds,
                    ...DB::table('projects')->where('tenant_id', $binding->tenant_id)->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                ),
                'module' => $projectIds[] = (int) DB::table('modules')->where('id', $binding->scope_id)->value('project_id'),
                'stage' => $projectIds[] = (int) DB::table('stages')->where('id', $binding->scope_id)->value('project_id'),
                'session' => $projectIds[] = (int) DB::table('requirement_sessions')->where('id', $binding->scope_id)->value('project_id'),
                default => null,
            };
        }

        return array_values(array_unique(array_filter($projectIds)));
    }

    /**
     * @return array<int, ScopeBinding>
     */
    private function coveringBindings(User $user, ScopeRef $ref): array
    {
        // Cache the user's active bindings per tenant; covers() then filters the
        // cached set to the specific ref in PHP — no per-row binding query.
        $cacheKey = $user->id.'|'.($ref->tenantId ?? 'null');
        $active = $this->bindingCache[$cacheKey] ??= ScopeBinding::active()
            ->where('user_id', $user->id)
            ->where('tenant_id', $ref->tenantId)
            ->get()
            ->all();

        return array_values(array_filter($active, fn (ScopeBinding $b): bool => $this->covers($b, $ref)));
    }

    private function covers(ScopeBinding $binding, ScopeRef $ref): bool
    {
        return match ($binding->scope_type) {
            'tenant' => true, // already filtered to same tenant
            'project' => (int) $binding->scope_id === $ref->projectId,
            'module' => (int) $binding->scope_id === $ref->moduleId,
            'stage' => (int) $binding->scope_id === $ref->stageId,
            'session' => (int) $binding->scope_id === $ref->sessionId,
            default => false,
        };
    }

    private function audit(Decision $decision, User $user, string $action, ?ScopeRef $ref, string $pep, ?string $field): Decision
    {
        $request = request();

        AccessAudit::create([
            'user_id' => $user->id,
            'actor_id' => auth()->id(),
            'ip' => $request->ip(),
            'request_id' => $request->header('X-Request-Id') ?? (string) str()->uuid(),
            'decision' => $decision->permitted ? 'permit' : 'deny',
            'action' => $action,
            'object_type' => $ref?->objectType,
            'object_id' => $ref?->scopeId,
            'field' => $field,
            'tenant_id' => $ref?->tenantId,
            'scope_type' => $ref?->scopeType,
            'scope_id' => $ref?->scopeId,
            'reason' => $decision->reason,
            'matched_rule_type' => $decision->matchedRuleType,
            'matched_rule_id' => $decision->matchedRuleId,
            'pep' => $pep,
        ]);

        return $decision;
    }
}
