<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->with('project')
            ->latest()
            ->paginate(30);

        if ($request->expectsJson() || $request->query('_format') === 'json') {
            return response()->json([
                'data' => $notifications->map(fn($n) => [
                    'id' => $n->id,
                    'message' => $n->message,
                    'type' => $n->type,
                    'read' => $n->read,
                    'project' => $n->project ? $n->project->only(['id', 'name']) : null,
                    'created_at' => $n->created_at->diffForHumans(),
                ]),
            ]);
        }

        return view('notifications', compact('notifications'));
    }

    public function markRead(Notification $notification)
    {
        if ($notification->user_id !== auth()->id()) {
            abort(403);
        }
        $notification->update(['read' => true]);

        return response()->json(['ok' => true]);
    }

    public function readAll()
    {
        Notification::where('user_id', auth()->id())
            ->where('read', false)
            ->update(['read' => true]);

        return back()->with('success', 'All notifications marked as read.');
    }

    public function unreadCount()
    {
        $count = Notification::where('user_id', auth()->id())
            ->where('read', false)
            ->count();

        return response()->json(['count' => $count]);
    }
}
