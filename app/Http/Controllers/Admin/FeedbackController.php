<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FeedbackItem;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', FeedbackItem::class);

        $query = FeedbackItem::with('submittedBy')->withCount('attachments');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        $items = $query->latest()->paginate(20)->withQueryString();

        $stats = [
            'open' => FeedbackItem::open()->count(),
            'bugs' => FeedbackItem::open()->where('type', 'bug')->count(),
            'features' => FeedbackItem::open()->where('type', 'feature')->count(),
            'high' => FeedbackItem::open()->where('priority', 'high')->count(),
        ];

        return view('admin.feedback.index', compact('items', 'stats'));
    }

    public function show(FeedbackItem $feedback)
    {
        $this->authorize('viewAny', FeedbackItem::class);

        $feedback->load('attachments', 'submittedBy');

        return view('admin.feedback.show', ['item' => $feedback]);
    }

    public function update(Request $request, FeedbackItem $feedback, NotificationService $notifications)
    {
        $this->authorize('triage', $feedback);

        $data = $request->validate([
            'status' => 'required|in:new,triaged,in_progress,resolved,closed,wont_fix',
            'priority' => 'required|in:low,medium,high',
            'admin_response' => 'nullable|string',
        ]);

        // Stamp resolved_at when the loop closes; clear it when reopened.
        if (in_array($data['status'], FeedbackItem::DONE_STATUSES, true)) {
            $data['resolved_at'] = $feedback->resolved_at ?? now();
        } else {
            $data['resolved_at'] = null;
        }

        $before = $feedback->only(['status', 'priority', 'admin_response', 'resolved_at']);
        $feedback->update($data);

        AuditLog::record('feedback.updated', 'FeedbackItem', $feedback->id, $before, $feedback->only(['status', 'priority', 'admin_response', 'resolved_at']));

        $notifications->notify(
            $feedback->submitted_by,
            'feedback.update',
            "Your {$feedback->type} '{$feedback->title}' is now {$feedback->status}."
        );

        return back()->with('success', 'Feedback updated.');
    }

    public function destroy(FeedbackItem $feedback)
    {
        $this->authorize('delete', $feedback);

        $feedback->delete(); // model deleting() event cleans up attachment files

        return redirect()->route('admin.feedback.index')->with('success', 'Feedback deleted.');
    }
}
