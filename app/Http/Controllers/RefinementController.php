<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AiSuggestion;
use App\Models\Graph\EngObject;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\Ai\Exceptions\RefinementException;
use App\Services\Ai\RefinementService;
use App\Services\Rag\RagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * AI refinement actions on a canonical object (spec §10). Requesting a
 * suggestion and deciding on it both require 'edit' — refinement mutates (or
 * declines to mutate) project content.
 */
class RefinementController extends Controller
{
    public function __construct(
        private readonly PolicyDecisionPoint $pdp,
        private readonly RefinementService $refinement,
    ) {}

    public function suggest(Request $request, EngObject $object): RedirectResponse
    {
        abort_unless($this->pdp->can($request->user(), 'edit', $object)->permitted, 403, 'Access denied by ACL.');

        $data = $request->validate([
            'action' => ['required', 'in:'.implode(',', RefinementService::ACTIONS)],
        ]);

        // check_objective degrades gracefully without keys; everything else needs AI on.
        if ($data['action'] !== 'check_objective' && ! RagService::enabled()) {
            return back()->with('error', 'AI is not enabled — an admin must configure API keys in Settings first.');
        }

        try {
            $suggestion = $this->refinement->suggest($object, $data['action'], $request->user());
        } catch (RefinementException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "AI suggestion #{$suggestion->id} ({$data['action']}) ready for your review below.");
    }

    public function decide(Request $request, AiSuggestion $suggestion): RedirectResponse
    {
        abort_unless($this->pdp->can($request->user(), 'edit', $suggestion->object)->permitted, 403, 'Access denied by ACL.');

        $data = $request->validate([
            'decision' => ['required', 'in:accept,accept_edit,reject'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:10000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->refinement->decide($suggestion, $data['decision'], $request->user(), [
                'title' => $data['title'] ?? null,
                'body' => $data['body'] ?? null,
                'note' => $data['note'] ?? null,
            ]);
        } catch (RefinementException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', match ($data['decision']) {
            'accept' => 'Suggestion accepted and applied.',
            'accept_edit' => 'Suggestion applied with your edits.',
            default => 'Suggestion rejected.',
        });
    }
}
