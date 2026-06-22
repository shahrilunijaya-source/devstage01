<?php

namespace App\Services\Rag\Tools;

trait ScopesProjects
{
    /**
     * Resolve the project ids a tool call may touch. If Claude named a project_id,
     * keep it ONLY if it is in the allowed set; otherwise fall back to all allowed.
     * Guarantees a tool can never read outside the caller's permission.
     *
     * @param  array<string,mixed>  $input
     * @param  array<int,int>  $allowed
     * @return array<int,int>
     */
    protected function resolveProjectIds(array $input, array $allowed): array
    {
        $requested = $input['project_id'] ?? null;
        if ($requested !== null && in_array((int) $requested, $allowed, true)) {
            return [(int) $requested];
        }

        return $allowed;
    }
}
