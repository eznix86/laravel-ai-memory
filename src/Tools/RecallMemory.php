<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Tools;

use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Prompt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RecallMemory implements Tool
{
    public const int CANDIDATES = 50;

    public const int RRF_K = 60;

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
     * Rank memories by keyword and by meaning, fuse both rankings and weigh them by strength.
     *
     * @return Collection<int, AgentMemory>
     */
    public function search(string $query, bool $includeArchived = false): Collection
    {
        $keywords = $this->candidates($includeArchived)
            ->whereFullText('content', $query, ['language' => config('memory.fulltext_language')])
            ->orderByRaw('ts_rank(to_tsvector(?::regconfig, content), plainto_tsquery(?::regconfig, ?)) desc', [config('memory.fulltext_language'), config('memory.fulltext_language'), $query])
            ->limit(self::CANDIDATES)
            ->pluck('id');

        $meanings = $this->candidates($includeArchived)
            ->whereVectorSimilarTo('embedding', $query, config('memory.recall_min_similarity'))
            ->limit(self::CANDIDATES)
            ->pluck('id');

        $scores = [];

        foreach ([$keywords, $meanings] as $ranking) {
            foreach ($ranking->values() as $rank => $id) {
                $scores[$id] = ($scores[$id] ?? 0) + 1 / (self::RRF_K + $rank + 1);
            }
        }

        return AgentMemory::query()
            ->whereKey(collect($scores)->keys())
            ->get()
            ->sortByDesc(fn (AgentMemory $memory): float => $scores[$memory->id] * $memory->strength())
            ->take(config('memory.recall_limit'))
            ->values();
    }

    /**
     * Start a query for the memories that can be recalled.
     *
     * @return Builder<AgentMemory>
     */
    protected function candidates(bool $includeArchived): Builder
    {
        if (! $includeArchived) {
            return AgentMemory::query()->forContext($this->context)->live();
        }

        return AgentMemory::query()
            ->forContext($this->context)
            ->whereNull('superseded_by')
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
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
