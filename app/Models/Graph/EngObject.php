<?php

declare(strict_types=1);

namespace App\Models\Graph;

use App\Enums\ConfidenceLevel;
use App\Enums\ObjectStatus;
use App\Enums\ObjectType;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\Stage;
use App\Models\Portfolio\StageBaseline;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A node in the canonical engineering object graph (PRD §12). Table `objects`;
 * model named EngObject because "Object" is reserved. Never hard-deleted —
 * canonical truth is soft-deleted only.
 */
class EngObject extends Model
{
    use SoftDeletes;

    protected $table = 'objects';

    protected $fillable = [
        'ref', 'type', 'title', 'body', 'attributes',
        'tenant_id', 'project_id', 'module_id', 'stage_id', 'session_id',
        'owner_user_id', 'source', 'source_object_id',
        'status', 'confidence', 'impact', 'classification',
        'current_version', 'effective_date', 'baseline_id', 'approval_id',
    ];

    protected $casts = [
        'type' => ObjectType::class,
        'status' => ObjectStatus::class,
        'confidence' => ConfidenceLevel::class,
        'attributes' => 'array',
        'effective_date' => 'date',
        'current_version' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function sourceObject(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_object_id');
    }

    public function baseline(): BelongsTo
    {
        return $this->belongsTo(StageBaseline::class, 'baseline_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ObjectVersion::class, 'object_id');
    }

    public function outgoingTraces(): HasMany
    {
        return $this->hasMany(TraceRelationship::class, 'from_object_id');
    }

    public function incomingTraces(): HasMany
    {
        return $this->hasMany(TraceRelationship::class, 'to_object_id');
    }

    /** @param  Builder<EngObject>  $query */
    public function scopeOfType(Builder $query, ObjectType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    /** @param  Builder<EngObject>  $query */
    public function scopeForProject(Builder $query, int $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }
}
