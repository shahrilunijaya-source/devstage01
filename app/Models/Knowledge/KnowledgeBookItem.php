<?php

declare(strict_types=1);

namespace App\Models\Knowledge;

use App\Enums\LifecycleStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single methodology entry: deliverable, question, matrix, gate, rule, etc.
 */
class KnowledgeBookItem extends Model
{
    protected $fillable = [
        'knowledge_book_id', 'item_type', 'code', 'stage',
        'parent_id', 'title', 'body', 'payload', 'sort_order',
    ];

    protected $casts = [
        'stage' => LifecycleStage::class,
        'payload' => 'array',
        'sort_order' => 'integer',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBook::class, 'knowledge_book_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
