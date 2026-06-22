<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Release extends Model
{
    protected $fillable = ['version', 'title', 'notes', 'git_sha', 'released_at'];

    protected $casts = [
        'notes' => 'array',
        'released_at' => 'datetime',
    ];

    /** The newest release — what the "What's New" modal shows. */
    public static function current(): ?self
    {
        return static::orderByDesc('released_at')->orderByDesc('id')->first();
    }

    /** Flattened display label, e.g. "v1.0.0 · build 122". */
    public function displayTitle(): string
    {
        return $this->title ?: ('v'.$this->version);
    }
}
