<?php

namespace App\Services\Rag\Tools;

use App\Models\ClaimMilestone;

class QueryClaimsTool implements ChatTool
{
    use ScopesProjects;

    public function name(): string
    {
        return 'query_claims';
    }

    public function description(): string
    {
        return 'Look up claim milestones: the next claim due, unpaid/pending claims, or totals (total value, received). Use for "next claim date", "which claims are unpaid", "total claimed vs received".';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => ['type' => 'integer', 'description' => 'Optional. Omit for all projects in scope.'],
                'mode' => ['type' => 'string', 'enum' => ['next_due', 'unpaid', 'totals', 'list'], 'description' => 'Default list.'],
            ],
        ];
    }

    public function handle(array $input, array $allowedProjectIds): array
    {
        $ids = $this->resolveProjectIds($input, $allowedProjectIds);
        $base = ClaimMilestone::query()->whereIn('project_id', $ids);

        $totalAmount = (clone $base)->sum('amount');
        $received = (clone $base)->where('claim_status', 'received')->sum('amount');

        $nextDue = (clone $base)
            ->whereNotNull('target_date')
            ->where('target_date', '>=', now()->startOfDay())
            ->where('claim_status', '!=', 'received')
            ->orderBy('target_date')
            ->first();

        $unpaid = (clone $base)->where('claim_status', '!=', 'received')->orderBy('target_date')->limit(50)->get();

        return [
            'totals' => [
                'amount' => (float) $totalAmount,
                'received' => (float) $received,
                'outstanding' => (float) ($totalAmount - $received),
            ],
            'next_due' => $nextDue ? $this->shape($nextDue) : null,
            'unpaid_count' => $unpaid->count(),
            'unpaid' => $unpaid->map(fn (ClaimMilestone $c) => $this->shape($c))->all(),
        ];
    }

    private function shape(ClaimMilestone $c): array
    {
        return [
            'project_id' => $c->project_id,
            'perkara' => $c->perkara,
            'amount' => (float) $c->amount,
            'target_date' => $c->target_date?->format('d/m/Y'),
            'claim_status' => $c->claim_status,
        ];
    }
}
