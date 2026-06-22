<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ChatSession;
use App\Models\Project;
use App\Models\User;
use App\Services\Rag\RagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Portfolio-wide chat (Phase 2). Same grounded flow as per-project chat, but
 * retrieval spans every project the user may see — all projects for
 * Admin/Director, only assigned projects for everyone else. Scoping the chunk
 * query to those ids is the access boundary; no cross-project leak is possible.
 */
class PortfolioChatController extends Controller
{
    public function index()
    {
        abort_unless(RagService::enabled(), 404);

        $session = $this->session();
        $messages = $session->messages()->get(['role', 'content', 'model', 'citations', 'grounded', 'created_at']);

        return view('portfolio-chat', compact('messages'));
    }

    public function ask(Request $request): JsonResponse
    {
        abort_unless(RagService::enabled(), 404);

        $data = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
            'model' => ['nullable', 'in:haiku,sonnet'],
        ]);

        $question = trim($data['question']);
        abort_if($question === '', 422, 'Question cannot be empty.');

        $projectIds = $this->accessibleProjectIds($request->user());

        $session = $this->session();
        $session->messages()->create(['role' => 'user', 'content' => $question]);

        try {
            $result = app(RagService::class)->ask($projectIds, $question, $data['model'] ?? 'haiku');
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'The AI service is unavailable right now. Please try again.'], 502);
        }

        $citations = $this->labelWithProject($result['citations']);

        $session->messages()->create([
            'role' => 'assistant',
            'content' => $result['answer'],
            'model' => $result['model'],
            'citations' => $citations,
            'grounded' => $result['grounded'],
            'tool_calls' => $result['tool_calls'] ?? null,
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'chat.ask',
            'entity_type' => 'Portfolio',
            'entity_id' => null,
            'project_id' => null,
            'after' => [
                'grounded' => $result['grounded'],
                'tools' => array_column($result['tool_calls'] ?? [], 'name'),
            ],
        ]);

        return response()->json([
            'answer' => $result['answer'],
            'citations' => $citations,
            'grounded' => $result['grounded'],
            'model' => $result['model'],
            'tool_calls' => $result['tool_calls'] ?? null,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function accessibleProjectIds(User $user): array
    {
        if ($user->isAdmin() || $user->isDirector()) {
            return Project::query()->pluck('id')->all();
        }

        return $user->projects()->pluck('projects.id')->all();
    }

    /**
     * Prefix each citation with its project code so portfolio answers make clear
     * which project a source belongs to.
     *
     * @param  array<int, array<string, mixed>>  $citations
     * @return array<int, array<string, mixed>>
     */
    private function labelWithProject(array $citations): array
    {
        $ids = array_values(array_unique(array_filter(array_column($citations, 'project_id'))));
        if ($ids === []) {
            return $citations;
        }

        $codes = Project::whereIn('id', $ids)->pluck('code', 'id');

        return array_map(function ($c) use ($codes) {
            $code = $codes[$c['project_id'] ?? null] ?? null;
            if ($code) {
                $c['source_label'] = "[{$code}] ".$c['source_label'];
            }

            return $c;
        }, $citations);
    }

    private function session(): ChatSession
    {
        return ChatSession::firstOrCreate(
            ['user_id' => auth()->id(), 'scope' => 'portfolio', 'project_id' => null],
        );
    }
}
