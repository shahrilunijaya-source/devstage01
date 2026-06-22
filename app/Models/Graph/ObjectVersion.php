<?php

declare(strict_types=1);

namespace App\Models\Graph;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Immutable, append-only object version snapshot (PRD §12.5). Updates and
 * deletes are blocked at the model layer; DB triggers add defence in depth later.
 */
class ObjectVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['object_id', 'version', 'snapshot', 'change_summary', 'changed_by'];

    protected $casts = [
        'snapshot' => 'array',
        'version' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException('object_versions rows are immutable and cannot be updated.');
        });

        static::deleting(function (): never {
            throw new RuntimeException('object_versions rows are immutable and cannot be deleted.');
        });
    }

    public function object(): BelongsTo
    {
        return $this->belongsTo(EngObject::class, 'object_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
