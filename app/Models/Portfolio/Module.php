<?php

declare(strict_types=1);

namespace App\Models\Portfolio;

use App\Enums\LifecycleStage;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One System = Many Modules (PRD §5). One Module runs all lifecycle stages, so
 * creating a Module auto-seeds the full stage set.
 */
class Module extends Model
{
    protected $fillable = ['project_id', 'name', 'code', 'status', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    protected static function booted(): void
    {
        static::created(function (Module $module): void {
            $module->seedStages();
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class);
    }

    /** Seed one Stage row per lifecycle stage, in order. Idempotent. */
    public function seedStages(): void
    {
        foreach (LifecycleStage::ordered() as $stage) {
            $this->stages()->firstOrCreate(
                ['stage' => $stage->value],
                ['project_id' => $this->project_id, 'status' => 'not_started'],
            );
        }
    }
}
