<?php

declare(strict_types=1);

namespace App\Models\Portfolio;

use App\Models\Graph\BaselineObject;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Immutable stage rollup + canonical-graph Baseline header (PRD §5, §12.5).
 * Member object-versions live in baseline_objects.
 */
class StageBaseline extends Model
{
    protected $fillable = [
        'stage_id', 'project_id', 'module_id', 'version_label', 'sequence',
        'status', 'knowledge_book_version', 'snapshot_meta',
        'approved_by', 'created_by', 'approved_at',
        'reopened_by', 'reopened_at', 'reopen_reason',
    ];

    protected $casts = [
        'snapshot_meta' => 'array',
        'sequence' => 'integer',
        'approved_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function reopener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function baselineObjects(): HasMany
    {
        return $this->hasMany(BaselineObject::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
