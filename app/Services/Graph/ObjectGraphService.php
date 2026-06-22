<?php

declare(strict_types=1);

namespace App\Services\Graph;

use App\Enums\ObjectStatus;
use App\Enums\ObjectType;
use App\Models\AuditLog;
use App\Models\Graph\EngObject;
use App\Models\Graph\ObjectVersion;
use App\Services\Graph\Exceptions\BaselinedObjectException;
use Illuminate\Support\Facades\DB;

/**
 * Create/update canonical objects (PRD §12). Every create writes version 1;
 * every update appends an immutable version snapshot and bumps current_version.
 */
class ObjectGraphService
{
    public function __construct(private readonly ObjectIdGenerator $ids) {}

    /**
     * @param  array<string, mixed>  $opts  body, attributes, module_id, stage_id,
     *                                      session_id, owner_user_id, source,
     *                                      source_object_id, status, confidence,
     *                                      impact, effective_date, changed_by,
     *                                      change_summary
     */
    public function create(ObjectType $type, int $tenantId, int $projectId, string $title, array $opts = []): EngObject
    {
        return DB::transaction(function () use ($type, $tenantId, $projectId, $title, $opts): EngObject {
            $ref = $this->ids->next($projectId, $type);

            $object = EngObject::create([
                'ref' => $ref,
                'type' => $type->value,
                'title' => $title,
                'body' => $opts['body'] ?? null,
                'attributes' => $opts['attributes'] ?? null,
                'tenant_id' => $tenantId,
                'project_id' => $projectId,
                'module_id' => $opts['module_id'] ?? null,
                'stage_id' => $opts['stage_id'] ?? null,
                'session_id' => $opts['session_id'] ?? null,
                'owner_user_id' => $opts['owner_user_id'] ?? null,
                'source' => $opts['source'] ?? null,
                'source_object_id' => $opts['source_object_id'] ?? null,
                'status' => $opts['status'] ?? ObjectStatus::NEEDS_CONFIRMATION,
                'confidence' => $opts['confidence'] ?? null,
                'impact' => $opts['impact'] ?? null,
                'classification' => $opts['classification'] ?? 'internal',
                'effective_date' => $opts['effective_date'] ?? null,
                'current_version' => 1,
            ]);

            $this->writeVersion($object, 1, $opts['change_summary'] ?? 'created', $opts['changed_by'] ?? null);

            AuditLog::record('object.created', 'EngObject', (int) $object->id, [], ['ref' => $ref]);

            return $object;
        });
    }

    /**
     * @param  array<string, mixed>  $changes  any fillable canonical field
     *
     * @throws BaselinedObjectException when editing a baselined object outside a
     *                                  controlled change request (PRD §13.10).
     */
    public function update(EngObject $object, array $changes, ?int $changedBy = null, ?string $summary = null, bool $allowBaselined = false): EngObject
    {
        if ($object->baseline_id !== null && ! $allowBaselined) {
            throw new BaselinedObjectException(
                "Object {$object->ref} is baselined and cannot be edited without a controlled change request.",
            );
        }

        return DB::transaction(function () use ($object, $changes, $changedBy, $summary): EngObject {
            $before = $this->snapshot($object);

            $object->fill($changes);
            $object->current_version = (int) $object->current_version + 1;
            $object->save();

            $this->writeVersion($object, (int) $object->current_version, $summary ?? 'updated', $changedBy);

            AuditLog::record('object.updated', 'EngObject', (int) $object->id, $before, $this->snapshot($object));

            return $object;
        });
    }

    private function writeVersion(EngObject $object, int $version, string $summary, ?int $changedBy): void
    {
        ObjectVersion::create([
            'object_id' => $object->id,
            'version' => $version,
            'snapshot' => $this->snapshot($object),
            'change_summary' => $summary,
            'changed_by' => $changedBy,
        ]);
    }

    /** @return array<string, mixed> Canonical state frozen into a version row. */
    private function snapshot(EngObject $object): array
    {
        return [
            'ref' => $object->ref,
            'type' => $object->type->value,
            'title' => $object->title,
            'body' => $object->body,
            'attributes' => $object->attributes,
            'status' => $object->status?->value,
            'confidence' => $object->confidence?->value,
            'impact' => $object->impact,
            'tenant_id' => $object->tenant_id,
            'project_id' => $object->project_id,
            'module_id' => $object->module_id,
            'stage_id' => $object->stage_id,
            'session_id' => $object->session_id,
            'effective_date' => $object->effective_date?->toDateString(),
            'version' => $object->current_version,
        ];
    }
}
