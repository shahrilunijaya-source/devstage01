<?php

namespace App\Services\Rag\Tools;

class ToolRegistry
{
    /** @param array<int,ChatTool> $tools */
    public function __construct(private array $tools) {}

    /**
     * Anthropic tool definitions for the Messages API.
     *
     * @return array<int,array<string,mixed>>
     */
    public function definitions(): array
    {
        return array_map(fn (ChatTool $t) => [
            'name' => $t->name(),
            'description' => $t->description(),
            'input_schema' => $t->schema(),
        ], $this->tools);
    }

    /**
     * @param  array<string,mixed>  $input
     * @param  array<int,int>  $allowedProjectIds
     * @return array<string,mixed>
     */
    public function run(string $name, array $input, array $allowedProjectIds): array
    {
        foreach ($this->tools as $tool) {
            if ($tool->name() === $name) {
                return $tool->handle($input, $allowedProjectIds);
            }
        }

        return ['error' => "Unknown tool: {$name}"];
    }
}
