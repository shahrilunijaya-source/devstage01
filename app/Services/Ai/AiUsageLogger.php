<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Support\Facades\DB;

/** Token spend log (spec §11). Best-effort — never blocks the feature. */
class AiUsageLogger
{
    public function log(?int $userId, ?int $projectId, string $feature, ?string $model, int $tokensIn, int $tokensOut): void
    {
        try {
            DB::table('ai_usage_log')->insert([
                'user_id' => $userId,
                'project_id' => $projectId,
                'feature' => $feature,
                'model' => $model,
                'tokens_in' => $tokensIn,
                'tokens_out' => $tokensOut,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
