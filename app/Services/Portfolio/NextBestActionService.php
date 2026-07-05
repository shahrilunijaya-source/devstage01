<?php

declare(strict_types=1);

namespace App\Services\Portfolio;

use App\Enums\ObjectStatus;
use App\Enums\ObjectType;
use App\Models\Discussion;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\Stage;
use App\Models\Project;
use App\Models\User;

/**
 * Next Best Action (spec §14.3): derives "what should be done next" from the
 * live state of existing services — no new tables, no stored todo list. Rules
 * are ordered by how hard they block progress; the panel shows the top few.
 */
class NextBestActionService
{
    private const MAX_ACTIONS = 5;

    public function __construct(
        private readonly ObjectiveService $objectives,
        private readonly StageGateService $gates,
    ) {}

    /**
     * @return array<int, array{label: string, detail: string, url: string}>
     */
    public function forProject(Project $project, User $user): array
    {
        $actions = [];

        // 1. The anchor: objective missing or unapproved.
        $objective = $this->objectives->objectiveFor($project);
        if ($objective === null) {
            $actions[] = $this->action('Capture the project objective',
                'Nothing anchors the requirement chain yet — the BRS gate checks for it.',
                route('objective.edit', $project));
        } elseif ($objective->status !== ObjectStatus::CONFIRMED_BY_EVIDENCE) {
            $actions[] = $this->action('Approve the project objective',
                "{$objective->ref} is captured but unapproved — it does not count at the BRS gate yet.",
                route('objective.show', $project));
        }

        // 2. Structure: no modules means no stages to work in.
        if ($project->modules()->count() === 0) {
            $actions[] = $this->action('Add the first module',
                'Modules carry the nine lifecycle stages — nothing can be captured without one.',
                route('portfolio.show', $project));
        }

        // 3. Blocked stages stop everything downstream of them.
        foreach (Stage::where('project_id', $project->id)->where('status', 'blocked')->with('module')->get() as $stage) {
            $actions[] = $this->action("Unblock {$stage->stage->label()} ({$stage->module?->name})",
                'A blocked stage holds its whole module — resolve or escalate.',
                route('stages.gate', $stage));
        }

        // 4. Sessions waiting on a human step.
        $sessions = Session::where('project_id', $project->id)
            ->whereIn('phase', ['pre_analysis', 'firewall_review', 'in_session', 'post_session'])
            ->get();

        foreach ($sessions as $session) {
            $actions[] = match ($session->phase) {
                'pre_analysis' => EngObject::where('session_id', $session->id)->where('type', ObjectType::EVIDENCE->value)->exists()
                    ? $this->action("Run AI pre-analysis on \"{$session->title}\"",
                        'Evidence is waiting to be drafted into findings and requirements.',
                        route('sessions.show', $session))
                    : $this->action("Add evidence to \"{$session->title}\"",
                        'The session has no source material yet — paste or upload evidence.',
                        route('sessions.show', $session)),
                'firewall_review' => $this->action("Review AI drafts at the quality firewall (\"{$session->title}\")",
                    'Mandatory human review before the session pack is presented.',
                    route('sessions.show', $session)),
                'in_session' => $this->action("Resolve captured items in \"{$session->title}\"",
                    EngObject::where('session_id', $session->id)->whereIn('status', StageGateService::UNRESOLVED)->count().' item(s) still need confirm / correct / complete / decide.',
                    route('sessions.show', $session)),
                'post_session' => $this->action("Approve session \"{$session->title}\"",
                    'All items resolved — the exit gate awaits an approver.',
                    route('sessions.show', $session)),
                default => null,
            };
        }

        // 5. Stages ready to baseline.
        foreach (Stage::where('project_id', $project->id)->whereNot('status', 'baselined')->with('module')->get() as $stage) {
            if ($stage->sessions()->where('status', 'approved')->exists() && $this->gates->readiness($stage)['ready']) {
                $actions[] = $this->action("Baseline {$stage->stage->label()} ({$stage->module?->name})",
                    'Every gate condition is met — freeze the stage.',
                    route('stages.gate', $stage));
            }
        }

        // 6. Open blocking discussions.
        foreach (Discussion::where('project_id', $project->id)->open()->where('blocking', true)->visibleTo($user)->get() as $discussion) {
            $actions[] = $this->action("Resolve blocking discussion: {$discussion->title}",
                'It holds the stage gate until resolved.',
                route('portfolio.show', $project));
        }

        // 7. Change-request fallout awaiting disposition.
        $flagged = EngObject::where('project_id', $project->id)
            ->where('attributes->impact', 'review_required')
            ->count();
        if ($flagged > 0) {
            $actions[] = $this->action("Disposition {$flagged} object(s) flagged by change requests",
                'Applied changes marked downstream objects review-required.',
                route('changes.index', $project));
        }

        // 8. Open defects break the RTM.
        $defects = EngObject::where('project_id', $project->id)
            ->where('type', ObjectType::DEFECT->value)
            ->where('attributes->state', 'open')
            ->count();
        if ($defects > 0) {
            $actions[] = $this->action("Fix {$defects} open defect(s)",
                'Failing verification marks affected requirements broken in the RTM.',
                route('verification.index', $project));
        }

        return array_slice(array_values(array_filter($actions)), 0, self::MAX_ACTIONS);
    }

    /** @return array{label: string, detail: string, url: string} */
    private function action(string $label, string $detail, string $url): array
    {
        return ['label' => $label, 'detail' => $detail, 'url' => $url];
    }
}
