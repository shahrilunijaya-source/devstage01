<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Jobs\SyncDriveDocsJob;
use App\Models\AuditLog;
use App\Models\ChatSession;
use App\Models\Project;
use App\Models\RagDocument;
use App\Services\Rag\RagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ChatController extends Controller
{
    public function index(Project $project)
    {
        $this->authorize('view', $project);
        abort_unless(RagService::enabled(), 404);

        $session = $this->session($project);
        $messages = $session->messages()->get(['role', 'content', 'model', 'citations', 'grounded', 'created_at']);

        $canSync = auth()->user()->can('manageWeekly', $project) && RagService::driveConfigured();

        return view('projects.chat', compact('project', 'messages', 'canSync'));
    }

    public function ask(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        abort_unless(RagService::enabled(), 404);

        $data = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
            'model' => ['nullable', 'in:haiku,sonnet'],
        ]);

        $question = trim($data['question']);
        abort_if($question === '', 422, 'Question cannot be empty.');

        $session = $this->session($project);
        $session->messages()->create(['role' => 'user', 'content' => $question]);

        try {
            $result = app(RagService::class)->ask([$project->id], $question, $data['model'] ?? 'haiku');
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'The AI service is unavailable right now. Please try again.'], 502);
        }

        $session->messages()->create([
            'role' => 'assistant',
            'content' => $result['answer'],
            'model' => $result['model'],
            'citations' => $result['citations'],
            'grounded' => $result['grounded'],
            'tool_calls' => $result['tool_calls'] ?? null,
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'chat.ask',
            'entity_type' => 'Project',
            'entity_id' => $project->id,
            'project_id' => $project->id,
            'after' => [
                'grounded' => $result['grounded'],
                'tools' => array_column($result['tool_calls'] ?? [], 'name'),
            ],
        ]);

        return response()->json([
            'answer' => $result['answer'],
            'citations' => $result['citations'],
            'grounded' => $result['grounded'],
            'model' => $result['model'],
            'tool_calls' => $result['tool_calls'] ?? null,
        ]);
    }

    public function syncDrive(Project $project): JsonResponse
    {
        $this->authorize('manageWeekly', $project);
        abort_unless(RagService::driveConfigured(), 404);

        SyncDriveDocsJob::dispatch($project->id);

        return response()->json(['status' => 'queued']);
    }

    public function syncStatus(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $documents = RagDocument::where('project_id', $project->id)
            ->orderBy('name')
            ->get(['name', 'status', 'indexed_at', 'error'])
            ->map(fn (RagDocument $d) => [
                'name' => $d->name,
                'status' => $d->status,
                'indexed_at' => $d->indexed_at?->toIso8601String(),
                'error' => $d->error,
            ]);

        return response()->json(['documents' => $documents]);
    }

    /**
     * One persistent per-project chat thread per user. History accumulates across
     * visits so a PM can scroll back to earlier answers.
     */
    private function session(Project $project): ChatSession
    {
        return ChatSession::firstOrCreate(
            ['project_id' => $project->id, 'user_id' => auth()->id(), 'scope' => 'project'],
        );
    }
}
