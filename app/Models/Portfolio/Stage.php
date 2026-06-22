<?php

declare(strict_types=1);

namespace App\Models\Portfolio;

use App\Enums\LifecycleStage;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One lifecycle stage of a Module (PRD §10). Many sessions roll up to one
 * stage baseline.
 */
class Stage extends Model
{
    protected $fillable = [
        'module_id', 'project_id', 'stage', 'status',
        'started_at', 'gate_passed_at', 'current_baseline_id',
    ];

    protected $casts = [
        'stage' => LifecycleStage::class,
        'started_at' => 'datetime',
        'gate_passed_at' => 'datetime',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    public function baselines(): HasMany
    {
        return $this->hasMany(StageBaseline::class);
    }

    public function currentBaseline(): BelongsTo
    {
        return $this->belongsTo(StageBaseline::class, 'current_baseline_id');
    }
}
