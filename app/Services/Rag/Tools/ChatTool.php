<?php

namespace App\Services\Rag\Tools;

/**
 * A read-only capability the chat assistant may call. Every implementation MUST
 * restrict its query to the $allowedProjectIds it is given — that set is the
 * access boundary, identical to retrieval scoping.
 */
interface ChatTool
{
    public function name(): string;

    public function description(): string;

    /** JSON Schema for the tool input (Anthropic `input_schema`). */
    public function schema(): array;

    /**
     * @param  array<string,mixed>  $input  validated-by-Claude tool input
     * @param  array<int,int>  $allowedProjectIds  the only projects this call may read
     * @return array<string,mixed> JSON-serialisable result handed back to Claude
     */
    public function handle(array $input, array $allowedProjectIds): array;
}
