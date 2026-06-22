<?php

declare(strict_types=1);

namespace App\Models\Graph;

use App\Enums\ObjectType;
use App\Models\Portfolio\StageBaseline;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single object frozen into a stage baseline at a specific version (PRD §12.5).
 */
class BaselineObject extends Model
{
    protected $fillable = ['stage_baseline_id', 'object_id', 'object_version', 'ref', 'type'];

    protected $casts = [
        'type' => ObjectType::class,
        'object_version' => 'integer',
    ];

    public function stageBaseline(): BelongsTo
    {
        return $this->belongsTo(StageBaseline::class);
    }

    public function object(): BelongsTo
    {
        return $this->belongsTo(EngObject::class, 'object_id');
    }
}
