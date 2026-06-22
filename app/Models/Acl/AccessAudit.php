<?php

declare(strict_types=1);

namespace App\Models\Acl;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Immutable, append-only access-decision record (PRD §6.4).
 */
class AccessAudit extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'acl_access_audit';

    protected $fillable = [
        'user_id', 'actor_id', 'decision', 'action', 'object_type', 'object_id',
        'field', 'tenant_id', 'scope_type', 'scope_id', 'reason',
        'matched_rule_type', 'matched_rule_id', 'pep', 'request_id', 'ip',
    ];

    protected $casts = ['created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException('acl_access_audit rows are immutable and cannot be updated.');
        });

        static::deleting(function (): never {
            throw new RuntimeException('acl_access_audit rows are immutable and cannot be deleted.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
