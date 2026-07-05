<?php

declare(strict_types=1);

namespace App\Models\Knowledge;

use App\Enums\LifecycleStage;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant/project-isolated knowledge: tailored questions, approved assumptions,
 * lessons, AI learnings (PRD §7.3).
 */
class ProjectKnowledgeItem extends Model
{
    protected $fillable = [
        'tenant_id', 'project_id', 'item_type', 'source_kb_item_id',
        'stage', 'module_id', 'title', 'body', 'payload', 'status', 'approved_by',
    ];

    protected $casts = [
        'stage' => LifecycleStage::class,
        'payload' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sourceKbItem(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBookItem::class, 'source_kb_item_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
