<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Services;

use Eznix86\AI\Memory\Embedder;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class MemorySearch
{
    public const int CANDIDATES = 50;

    /**
     * Rank memories by meaning, and break ties by strength.
     *
     * @param  array<int, int>  $except
     * @return Collection<int, AgentMemory>
     */
    public function rank(MemoryContext $context, string $query, int $limit, bool $includeArchived = false, array $except = []): Collection
    {
        $vector = app(Embedder::class)->embed($query);

        return $this->candidates($context, $includeArchived, $except)
            ->select('*')
            ->selectVectorDistance('embedding', $vector, 'distance')
            ->whereVectorSimilarTo('embedding', $vector, config('memory.recall_min_similarity'))
            ->limit(self::CANDIDATES)
            ->get()
            ->sort(fn (AgentMemory $a, AgentMemory $b): int => [round((float) $a->getAttribute('distance'), 6), $b->strength()] <=> [round((float) $b->getAttribute('distance'), 6), $a->strength()])
            ->take($limit)
            ->values();
    }

    /**
     * Start a query for the memories that can be found.
     *
     * @param  array<int, int>  $except
     * @return Builder<AgentMemory>
     */
    protected function candidates(MemoryContext $context, bool $includeArchived, array $except): Builder
    {
        $query = AgentMemory::query()->forContext($context)->whereKeyNot($except);

        if (! $includeArchived) {
            return $query->active();
        }

        return $query
            ->whereNull('superseded_by')
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
