<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedbackAttachment extends Model
{
    protected $fillable = [
        'feedback_item_id', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes',
    ];

    protected $casts = ['size_bytes' => 'integer'];

    public function feedbackItem()
    {
        return $this->belongsTo(FeedbackItem::class);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with((string) $this->mime_type, 'video/');
    }

    public function humanSize(): string
    {
        $b = $this->size_bytes;

        if ($b >= 1048576) {
            return round($b / 1048576, 1).' MB';
        }
        if ($b >= 1024) {
            return round($b / 1024).' KB';
        }

        return $b.' B';
    }

    public function downloadUrl(string $disposition = 'inline'): string
    {
        return route('feedback.attachments.download', ['attachment' => $this, 'disposition' => $disposition]);
    }
}
