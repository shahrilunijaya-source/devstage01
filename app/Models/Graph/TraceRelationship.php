<?php

declare(strict_types=1);

namespace App\Models\Graph;

use App\Enums\RelationType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A directed trace edge between two canonical objects (PRD §12.3).
 */
class TraceRelationship extends Model
{
    protected $fillable = ['from_object_id', 'to_object_id', 'relation_type', 'project_id', 'note', 'created_by'];

    protected $casts = ['relation_type' => RelationType::class];

    public function fromObject(): BelongsTo
    {
        return $this->belongsTo(EngObject::class, 'from_object_id');
    }

    public function toObject(): BelongsTo
    {
        return $this->belongsTo(EngObject::class, 'to_object_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
