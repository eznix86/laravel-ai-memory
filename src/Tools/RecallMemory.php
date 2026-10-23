<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Tools;

use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RecallMemory implements Tool
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
        return trim(view('prompts.memory.tools.recall-memory')->render());
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $memories = AgentMemory::query()
            ->forContext($this->context)
            ->live()
            ->whereVectorSimilarTo('embedding', (string) $request['query'], config('memory.recall_min_similarity'))
            ->limit(config('memory.recall_limit'))
            ->get();

        if ($memories->isEmpty()) {
            return 'No relevant memories found.';
        }

        AgentMemory::query()->whereKey($memories->modelKeys())->toBase()->increment('recall_count', 1, ['last_used_at' => now()]);

        return trim(view('prompts.memory.tools.recall-memory-results', ['memories' => $memories])->render());
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('What to search for.')
                ->required(),
        ];
    }
}
