<?php

declare(strict_types=1);

namespace App\Services\AccessControl;

use App\Models\Graph\EngObject;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\Stage;
use App\Models\Portfolio\StageBaseline;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Maps any domain model to a normalized ScopeRef (PRD §6.2). Returns null for
 * objects that carry no scope (the PDP then abstains).
 */
class ScopeResolver
{
    public function resolve(mixed $object): ?ScopeRef
    {
        return match (true) {
            $object instanceof Project => new ScopeRef(
                $object->tenant_id, 'project', $object->id, 'project', $object->id,
            ),
            $object instanceof Module => new ScopeRef(
                $this->tenantOf($object->project_id), 'module', $object->id, 'module',
                $object->project_id, $object->id,
            ),
            $object instanceof Stage => new ScopeRef(
                $this->tenantOf($object->project_id), 'stage', $object->id, 'stage',
                $object->project_id, $object->module_id, $object->id,
            ),
            $object instanceof Session => new ScopeRef(
                $this->tenantOf($object->project_id), 'session', $object->id, 'session',
                $object->project_id, $object->module_id, $object->stage_id, $object->id,
            ),
            $object instanceof StageBaseline => new ScopeRef(
                $this->tenantOf($object->project_id), 'stage', $object->stage_id, 'baseline',
                $object->project_id, $object->module_id, $object->stage_id,
            ),
            $object instanceof EngObject => new ScopeRef(
                $object->tenant_id, 'object', $object->id, $object->type->value,
                $object->project_id, $object->module_id, $object->stage_id, $object->session_id,
                $object->status?->value, $object->confidence?->value, $object->classification,
            ),
            default => null,
        };
    }

    private function tenantOf(?int $projectId): ?int
    {
        if ($projectId === null) {
            return null;
        }

        $tenantId = DB::table('projects')->where('id', $projectId)->value('tenant_id');

        return $tenantId !== null ? (int) $tenantId : null;
    }
}
