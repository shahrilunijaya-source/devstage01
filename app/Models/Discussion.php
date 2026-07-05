<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A contextual discussion thread (spec §12) on a Project, Stage, Session,
 * StageBaseline or EngObject. `visibility` separates internal notes from
 * client-visible conversation; `blocking` open discussions hold the stage gate.
 */
class Discussion extends Model
{
    protected $fillable = [
        'tenant_id', 'project_id', 'discussable_type', 'discussable_id',
        'title', 'visibility', 'blocking', 'status',
        'opened_by', 'assigned_to', 'due_at', 'resolved_by', 'resolved_at',
    ];

    protected $casts = [
        'blocking' => 'boolean',
        'due_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function discussable(): MorphTo
    {
        return $this->morphTo();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(DiscussionComment::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** Client users only ever see client-visible threads — enforced in the query. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isClient() ? $query->where('visibility', 'client') : $query;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }
}
