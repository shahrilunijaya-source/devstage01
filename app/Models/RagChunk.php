<?php

namespace App\Models;

use App\Models\Portfolio\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RagChunk extends Model
{
    protected $fillable = [
        'project_id', 'tenant_id', 'scope', 'source_type', 'source_id', 'source_label',
        'chunk_text', 'embedding', 'token_count', 'content_hash',
    ];

    protected $casts = [
        'embedding' => 'array',
        'token_count' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
