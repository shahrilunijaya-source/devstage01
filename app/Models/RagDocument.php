<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RagDocument extends Model
{
    protected $fillable = [
        'project_id', 'gdrive_file_id', 'name', 'mime',
        'modified_time', 'indexed_at', 'status', 'error',
    ];

    protected $casts = [
        'modified_time' => 'datetime',
        'indexed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
