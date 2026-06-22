<?php

declare(strict_types=1);

namespace App\Models\Graph;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A controlled change request against a canonical object (PRD §9.3.3).
 */
class ChangeRequest extends Model
{
    protected $fillable = [
        'ref', 'project_id', 'target_object_id', 'raised_by', 'title', 'description',
        'proposed_changes', 'impact', 'status', 'decided_by', 'decided_at', 'applied_at',
    ];

    protected $casts = [
        'proposed_changes' => 'array',
        'impact' => 'array',
        'decided_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(EngObject::class, 'target_object_id');
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
