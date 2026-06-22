<?php

declare(strict_types=1);

namespace App\Models\Knowledge;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pins a project to a KRISA Knowledge Book version (PRD §7.4).
 */
class ProjectKnowledgePin extends Model
{
    protected $fillable = [
        'project_id', 'knowledge_book_id', 'methodology_book_id',
        'pinned_at', 'pinned_by', 'overrides',
    ];

    protected $casts = [
        'overrides' => 'array',
        'pinned_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function knowledgeBook(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBook::class, 'knowledge_book_id');
    }

    public function methodologyBook(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBook::class, 'methodology_book_id');
    }

    public function pinnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by');
    }
}
