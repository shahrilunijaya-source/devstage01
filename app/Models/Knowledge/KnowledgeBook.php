<?php

declare(strict_types=1);

namespace App\Models\Knowledge;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A versioned, shared/global knowledge book — KRISA or Methodology (PRD §7.1-7.2).
 */
class KnowledgeBook extends Model
{
    protected $fillable = ['kind', 'version', 'title', 'status', 'published_at', 'notes'];

    protected $casts = ['published_at' => 'datetime'];

    public function items(): HasMany
    {
        return $this->hasMany(KnowledgeBookItem::class);
    }

    public function pins(): HasMany
    {
        return $this->hasMany(ProjectKnowledgePin::class);
    }
}
