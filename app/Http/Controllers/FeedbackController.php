<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\FeedbackAttachment;
use App\Models\FeedbackItem;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FeedbackController extends Controller
{
    /** The submitter's own bug reports + feature requests. */
    public function index()
    {
        $this->authorize('create', FeedbackItem::class);

        $items = FeedbackItem::with('attachments')
            ->where('submitted_by', auth()->id())
            ->latest()
            ->paginate(20);

        return view('feedback.index', compact('items'));
    }

    public function store(Request $request, NotificationService $notifications)
    {
        $this->authorize('create', FeedbackItem::class);

        $data = $request->validate([
            'type' => 'required|in:bug,feature',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'page_url' => 'nullable|string|max:2048',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => [
                'file',
                'max:51200', // 50 MB per file
                'mimes:jpg,jpeg,png,gif,webp,mp4,mov,webm,pdf,doc,docx,xls,xlsx,txt',
            ],
        ]);

        $item = FeedbackItem::create([
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => 'new',
            'priority' => 'medium',
            'submitted_by' => auth()->id(),
            'page_url' => $data['page_url'] ?? null,
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);

        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store('feedback/'.$item->id, 'local');

            $item->attachments()->create([
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        }

        $notifications->notifyTriagers(
            'feedback.new',
            "New {$item->type}: {$item->title}"
        );

        AuditLog::record('feedback.created', 'FeedbackItem', $item->id, [], $item->toArray());

        return redirect()
            ->route('feedback.show', $item)
            ->with('success', 'Thanks — your '.($item->type === 'bug' ? 'bug report' : 'feature request').' has been submitted.');
    }

    public function show(FeedbackItem $feedback)
    {
        $this->authorize('view', $feedback);

        $feedback->load('attachments', 'submittedBy');

        return view('feedback.show', ['item' => $feedback]);
    }

    /** Access-controlled stream/download from the PRIVATE disk. */
    public function download(FeedbackAttachment $attachment, Request $request)
    {
        $this->authorize('view', $attachment->feedbackItem);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        if ($request->query('disposition') === 'attachment') {
            return $disk->download($attachment->path, $attachment->original_name);
        }

        return $disk->response(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type ?? 'application/octet-stream']
        );
    }
}
