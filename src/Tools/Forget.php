<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Tools;

use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class Forget implements Tool
{
    /**
     * Create a new tool instance.
     */
    public function __construct(
        protected MemoryContext $context,
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return trim(view('prompts.memory.tools.forget')->render());
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $memory = AgentMemory::query()
            ->forContext($this->context)
            ->whereNull('archived_at')
            ->find((int) $request['memory_id']);

        if ($memory === null) {
            return "No memory #{$request['memory_id']} to forget.";
        }

        $memory->update(['archived_at' => now()]);

        return "Forgot #{$memory->id}.";
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'memory_id' => $schema->integer()
                ->description('The id of the memory, as shown after the # sign.')
                ->required(),
        ];
    }
}
