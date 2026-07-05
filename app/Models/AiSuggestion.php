<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Graph\EngObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stored AI proposal (spec §10). Never applied automatically — a human
 * accepts, accepts-with-edits, or rejects; the decision is recorded here and
 * any content change flows through ObjectGraphService as a normal version.
 */
class AiSuggestion extends Model
{
    protected $fillable = [
        'tenant_id', 'project_id', 'object_id', 'action', 'status', 'payload',
        'model', 'prompt_version', 'created_by', 'decided_by', 'decided_at', 'decision_note',
    ];

    protected $casts = [
        'payload' => 'array',
        'decided_at' => 'datetime',
    ];

    public function object(): BelongsTo
    {
        return $this->belongsTo(EngObject::class, 'object_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
