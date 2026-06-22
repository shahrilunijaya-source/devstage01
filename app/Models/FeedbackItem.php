<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class FeedbackItem extends Model
{
    protected $fillable = [
        'type', 'title', 'description', 'status', 'priority',
        'submitted_by', 'admin_response', 'resolved_at', 'page_url', 'user_agent',
    ];

    protected $casts = ['resolved_at' => 'datetime'];

    public const TYPES = ['bug', 'feature'];

    public const STATUSES = ['new', 'triaged', 'in_progress', 'resolved', 'closed', 'wont_fix'];

    public const PRIORITIES = ['low', 'medium', 'high'];

    /** Statuses considered still-actionable (the "open" definition). */
    public const OPEN_STATUSES = ['new', 'triaged', 'in_progress'];

    /** Statuses that close the loop and stamp resolved_at. */
    public const DONE_STATUSES = ['resolved', 'closed', 'wont_fix'];

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function attachments()
    {
        return $this->hasMany(FeedbackAttachment::class);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    protected static function booted(): void
    {
        // DB cascade removes attachment rows, but not the files on disk.
        static::deleting(function (FeedbackItem $item) {
            foreach ($item->attachments as $attachment) {
                Storage::disk($attachment->disk)->delete($attachment->path);
            }
            Storage::disk('local')->deleteDirectory('feedback/'.$item->id);
        });
    }
}
