<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Tools;

use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Prompt;
use Eznix86\AI\Memory\Services\MemorySearch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection;
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
        return Prompt::render('prompts.memory.tools.recall-memory');
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $memories = $this->search((string) $request['query'], (bool) ($request['include_archived'] ?? false));

        if ($memories->isEmpty()) {
            return __('memory::tools.nothing_found');
        }

        AgentMemory::query()->whereKey($memories->modelKeys())->toBase()->increment('recall_count', 1, ['last_used_at' => now()]);

        return Prompt::render('prompts.memory.tools.recall-memory-results', ['memories' => $memories]);
    }

    /**
     * Find the memories most relevant to the query.
     *
     * @return Collection<int, AgentMemory>
     */
    public function search(string $query, bool $includeArchived = false): Collection
    {
        return app(MemorySearch::class)->rank($this->context, $query, config('memory.recall_limit'), $includeArchived);
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
                ->description(__('memory::schema.query'))
                ->required(),
            'include_archived' => $schema->boolean()
                ->description(__('memory::schema.include_archived')),
        ];
    }
}
