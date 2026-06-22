<?php

declare(strict_types=1);

namespace App\Services\AccessControl;

use App\Models\Acl\FieldRule;
use App\Models\Acl\ObjectRule;
use App\Models\Acl\Permission;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use Illuminate\Support\Facades\DB;

/**
 * Seeds ACL reference data (roles, permissions, grants) and backfills scope
 * bindings from existing project assignments (PRD §6.3). Fully idempotent.
 */
class AclProvisioner
{
    /** Global role → granted actions on object_type `project`. */
    private const ROLE_GRANTS = [
        'admin' => ['*'],
        'director' => ['view', 'retrieve', 'export'],
        'project_pm' => ['view', 'edit', 'retrieve', 'validate', 'approve', 'baseline', 'assign', 'export'],
        'project_pe' => ['view', 'edit', 'retrieve', 'validate', 'export'],
        'project_member' => ['view', 'retrieve'],
        'client' => ['view', 'retrieve'],
    ];

    private const ROLE_NAMES = [
        'admin' => 'Administrator',
        'director' => 'Director',
        'project_pm' => 'Project Manager',
        'project_pe' => 'Project Engineer',
        'project_member' => 'Project Member',
        'client' => 'Client',
    ];

    private const ALL_ACTIONS = ['view', 'edit', 'retrieve', 'validate', 'approve', 'baseline', 'assign', 'export'];

    private const PROJECT_ROLE_MAP = [
        'pm' => 'project_pm',
        'pe' => 'project_pe',
        'member' => 'project_member',
        'client' => 'client',
    ];

    public function provision(): void
    {
        $permissions = $this->seedPermissions();
        $this->seedRoles($permissions);
    }

    /** Seed default attribute-based object and field rules (PRD §6.3). Idempotent. */
    public function seedRules(): void
    {
        foreach (['view', 'edit'] as $action) {
            ObjectRule::firstOrCreate(
                ['object_type' => '*', 'action' => $action, 'match_classification' => 'restricted', 'effect' => 'deny'],
                ['priority' => 100],
            );
        }

        FieldRule::firstOrCreate(
            ['object_type' => '*', 'field' => 'body', 'classification' => 'confidential', 'effect' => 'redact'],
            ['priority' => 100],
        );
    }

    /** @return array<string, Permission> keyed by "{action}:{object_type}" */
    private function seedPermissions(): array
    {
        $map = [];

        foreach (self::ALL_ACTIONS as $action) {
            foreach (['project', '*'] as $objectType) {
                $key = Permission::makeKey($action, $objectType);
                $map[$key] = Permission::firstOrCreate(
                    ['action' => $action, 'object_type' => $objectType],
                    ['key' => $key],
                );
            }
        }

        return $map;
    }

    /** @param  array<string, Permission>  $permissions */
    private function seedRoles(array $permissions): void
    {
        foreach (self::ROLE_GRANTS as $key => $actions) {
            $role = Role::firstOrCreate(
                ['tenant_id' => null, 'key' => $key],
                ['name' => self::ROLE_NAMES[$key], 'is_system' => true],
            );

            $resolvedActions = $actions === ['*'] ? self::ALL_ACTIONS : $actions;
            // Grant on the wildcard object type so a role's actions cover every
            // object within its bound scope (project, module, graph object, …),
            // refined further by object/field rules.
            foreach ($resolvedActions as $action) {
                $permission = $permissions[Permission::makeKey($action, '*')]
                    ?? $permissions[Permission::makeKey($action, 'project')];

                $role->permissions()->syncWithoutDetaching([
                    $permission->id => ['effect' => 'permit'],
                ]);
            }
        }
    }

    /** Mirror active project_assignments into project-scoped bindings. */
    public function backfillBindings(): int
    {
        $count = 0;

        $rows = DB::table('project_assignments')
            ->join('projects', 'project_assignments.project_id', '=', 'projects.id')
            ->whereNull('project_assignments.removed_at')
            ->get([
                'project_assignments.user_id',
                'project_assignments.project_id',
                'project_assignments.project_role',
                'project_assignments.assigned_by',
                'projects.tenant_id',
            ]);

        foreach ($rows as $row) {
            $roleKey = self::PROJECT_ROLE_MAP[$row->project_role] ?? null;

            if ($roleKey === null || $row->tenant_id === null) {
                continue;
            }

            $roleId = Role::where('key', $roleKey)->whereNull('tenant_id')->value('id');

            $binding = ScopeBinding::firstOrCreate(
                [
                    'user_id' => $row->user_id,
                    'role_id' => $roleId,
                    'scope_type' => 'project',
                    'scope_id' => $row->project_id,
                ],
                [
                    'tenant_id' => $row->tenant_id,
                    'granted_by' => $row->assigned_by,
                ],
            );

            if ($binding->wasRecentlyCreated) {
                $count++;
            }
        }

        return $count;
    }
}
