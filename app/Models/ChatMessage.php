<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'chat_session_id', 'role', 'content', 'model', 'citations', 'grounded', 'tool_calls',
    ];

    protected $casts = [
        'citations' => 'array',
        'grounded' => 'boolean',
        'tool_calls' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }
}
