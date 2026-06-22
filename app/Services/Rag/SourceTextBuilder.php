<?php

namespace App\Services\Rag;

use App\Models\ClaimMilestone;
use App\Models\Issue;
use App\Models\LedgerEntry;
use App\Models\Project;
use App\Models\ProjectComment;
use App\Models\ProjectWeekNote;
use App\Models\WbsItem;
use App\Models\WeeklyUpdate;
use Illuminate\Database\Eloquent\Model;

/**
 * Turns a stored DB row into the plain text we embed, plus a human-readable
 * citation label. One place that knows the column layout of every indexable
 * source — keep it in sync with the models it reads.
 */
class SourceTextBuilder
{
    /**
     * source_type => Eloquent model class. Drives the observer, the reindex job,
     * and this builder so they never disagree. 'document' and 'project_meta' are
     * handled separately (Drive files / the Project row itself).
     */
    public const MODEL_MAP = [
        'comment' => ProjectComment::class,
        'weekly_update' => WeeklyUpdate::class,
        'week_note' => ProjectWeekNote::class,
        'issue' => Issue::class,
        'wbs' => WbsItem::class,
        'ledger' => LedgerEntry::class,
        'claim' => ClaimMilestone::class,
    ];

    public static function sourceTypeFor(Model $model): ?string
    {
        if ($model instanceof Project) {
            return 'project_meta';
        }

        return array_search($model::class, self::MODEL_MAP, true) ?: null;
    }

    public static function modelClassFor(string $sourceType): ?string
    {
        return self::MODEL_MAP[$sourceType] ?? null;
    }

    /**
     * @return array{label: string, text: string} empty text => nothing to index
     */
    public function build(string $sourceType, Model $row): array
    {
        return match ($sourceType) {
            'comment' => $this->comment($row),
            'weekly_update' => $this->weeklyUpdate($row),
            'week_note' => $this->weekNote($row),
            'issue' => $this->issue($row),
            'wbs' => $this->wbs($row),
            'ledger' => $this->ledger($row),
            'claim' => $this->claim($row),
            'project_meta' => $this->projectMeta($row),
            default => ['label' => '', 'text' => ''],
        };
    }

    private function comment(ProjectComment $c): array
    {
        $who = $c->user?->name ?? 'Unknown';
        $when = $c->created_at?->format('d/m/Y') ?? '';

        return ['label' => trim("Comment by {$who} {$when}"), 'text' => (string) $c->body];
    }

    private function weeklyUpdate(WeeklyUpdate $w): array
    {
        $parts = [];
        if (filled($w->narrative_last_week)) {
            $parts[] = 'Last week: '.$w->narrative_last_week;
        }
        if (filled($w->blockers)) {
            $parts[] = 'Blockers: '.$w->blockers;
        }
        if (filled($w->status)) {
            $parts[] = 'Status: '.$w->status;
        }

        $date = $w->week_ending?->format('d/m/Y') ?? '';

        return ['label' => trim("Weekly Update {$date}"), 'text' => implode("\n", $parts)];
    }

    private function weekNote(ProjectWeekNote $n): array
    {
        $lines = [];
        $this->appendJsonLines($lines, 'Actual', $n->daily_actuals);
        $this->appendJsonLines($lines, 'Plan', $n->daily_plans);
        $this->appendJsonLines($lines, 'Staff', $n->staff_allocation);
        $this->appendJsonLines($lines, 'Finance', $n->finance_lines);

        $date = $n->week_start?->format('d/m/Y') ?? '';

        return ['label' => trim("Week Note {$date}"), 'text' => implode("\n", $lines)];
    }

    private function issue(Issue $i): array
    {
        $parts = array_filter([
            $i->title,
            $i->description,
            filled($i->severity) ? 'Severity: '.$i->severity : null,
            filled($i->status) ? 'Status: '.$i->status : null,
            $i->reported_date ? 'Reported: '.$i->reported_date->format('d/m/Y') : null,
            filled($i->resolution) ? 'Resolution: '.$i->resolution : null,
            $i->resolved_date ? 'Resolved: '.$i->resolved_date->format('d/m/Y') : null,
        ]);

        return ['label' => "Issue #{$i->id}: ".($i->title ?: 'Untitled'), 'text' => implode("\n", $parts)];
    }

    private function wbs(WbsItem $w): array
    {
        $parts = array_filter([
            trim(($w->wbs_code ? $w->wbs_code.' ' : '').$w->name),
            filled($w->notes) ? 'Notes: '.$w->notes : null,
            filled($w->pic) ? 'PIC: '.$w->pic : null,
            $w->is_milestone ? 'Milestone: yes' : null,
            $w->planned_start ? 'Planned start: '.$w->planned_start->format('d/m/Y') : null,
            $w->planned_end ? 'Planned end: '.$w->planned_end->format('d/m/Y') : null,
            $w->actual_pct !== null ? 'Actual progress: '.$w->actual_pct.'%' : null,
        ]);

        // A bare WBS row with only a name carries little signal; index when there
        // is a name (queryable task list). Empty name => skip.
        $label = 'WBS '.trim(($w->wbs_code ? $w->wbs_code.': ' : '').($w->name ?: ''));

        return ['label' => trim($label), 'text' => $w->name ? implode("\n", $parts) : ''];
    }

    private function ledger(LedgerEntry $l): array
    {
        if (blank($l->description)) {
            return ['label' => '', 'text' => ''];
        }

        $date = $l->txn_date?->format('d/m/Y') ?? '';
        $text = trim("{$l->category} {$l->txn_type}: {$l->description} (RM {$l->amount}) {$date}");

        return ['label' => trim("Ledger {$date}"), 'text' => $text];
    }

    private function claim(ClaimMilestone $c): array
    {
        $deliverables = is_array($c->deliverables) ? implode('; ', array_filter($c->deliverables)) : '';
        $parts = array_filter([
            $c->perkara,
            filled($deliverables) ? 'Deliverables: '.$deliverables : null,
            $c->percentage ? 'Percentage: '.$c->percentage.'%' : null,
            $c->amount ? 'Amount: RM '.$c->amount : null,
            $c->target_date ? 'Target/claim date: '.$c->target_date->format('d/m/Y') : null,
            filled($c->claim_status) ? 'Status: '.$c->claim_status : null,
            $c->received_date ? 'Received: '.$c->received_date->format('d/m/Y') : null,
            filled($c->notes) ? 'Notes: '.$c->notes : null,
        ]);

        return ['label' => 'Claim: '.($c->perkara ?: "#{$c->id}"), 'text' => implode("\n", $parts)];
    }

    private function projectMeta(Project $p): array
    {
        $fmt = fn ($d) => $d?->format('d/m/Y') ?? '';
        $lines = array_filter([
            "Project code: {$p->code}",
            "Name: {$p->name}",
            filled($p->description) ? "Description: {$p->description}" : null,
            filled($p->client) ? "Client: {$p->client}" : null,
            filled($p->procurement_method) ? "Procurement method: {$p->procurement_method}" : null,
            filled($p->contractor) ? "Contractor: {$p->contractor}" : null,
            $p->contract_value ? "Contract value: RM {$p->contract_value}" : null,
            $p->sst_date ? 'SST date: '.$fmt($p->sst_date) : null,
            ($p->contract_start_date || $p->contract_end_date)
                ? 'Contract period: '.$fmt($p->contract_start_date).' - '.$fmt($p->contract_end_date) : null,
            $p->bond_value ? "Bond value: RM {$p->bond_value}" : null,
        ]);

        return ['label' => 'Project Profile', 'text' => implode("\n", $lines)];
    }

    private function appendJsonLines(array &$lines, string $prefix, mixed $value): void
    {
        if (! is_array($value)) {
            return;
        }
        foreach ($value as $row) {
            $text = is_array($row) ? implode(' ', array_filter($row, 'is_scalar')) : (is_scalar($row) ? (string) $row : '');
            $text = trim($text);
            if ($text !== '') {
                $lines[] = "{$prefix}: {$text}";
            }
        }
    }
}
