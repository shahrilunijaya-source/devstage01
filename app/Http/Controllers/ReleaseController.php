<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Release;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "What's New" release feed. The release rows themselves are populated
 * automatically from conventional commits (releases:generate → changelog.json →
 * releases:sync); this controller only exposes the read view and records which
 * version each user has already seen so the unseen badge clears.
 */
class ReleaseController extends Controller
{
    /** Full release history, newest first — the "View all updates" page. */
    public function index(Request $request): View
    {
        /** @var LengthAwarePaginator $releases */
        $releases = Release::orderByDesc('released_at')->orderByDesc('id')->paginate(20);

        // Viewing the feed counts as seeing the latest release (clears the badge).
        if ($current = Release::current()) {
            $this->markSeen($request, $current->version);
        }

        return view('releases.index', ['releases' => $releases]);
    }

    /**
     * Record that the current user has seen a release version (clears the badge).
     * Validates inline and always answers in JSON: this app only auto-renders JSON
     * exceptions for api/* (see bootstrap/app.php), and the caller is a fetch().
     */
    public function seen(Request $request): JsonResponse
    {
        $version = $request->input('version');

        if (! is_string($version) || $version === '' || mb_strlen($version) > 50) {
            return response()->json([
                'message' => 'A valid version is required.',
                'errors' => ['version' => ['The version field is required.']],
            ], 422);
        }

        $this->markSeen($request, $version);

        return response()->json(['seen' => $version]);
    }

    private function markSeen(Request $request, string $version): void
    {
        $user = $request->user();

        if ($user && $user->last_seen_version !== $version) {
            $user->forceFill(['last_seen_version' => $version])->save();
        }
    }
}
