<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Services;

use Eznix86\AI\Memory\Embedder;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Str;

class MemorySearch
{
    public const int CANDIDATES = 50;

    public const int RRF_K = 60;

    /**
     * Rank memories by keyword, matching any word of the query, and by meaning, then fuse both rankings and break ties by strength.
     *
     * @param  array<int, int>  $except
     * @return Collection<int, AgentMemory>
     */
    public function rank(MemoryContext $context, string $query, int $limit, bool $includeArchived = false, array $except = []): Collection
    {
        $language = config('memory.fulltext_language');
        $vector = app(Embedder::class)->embed($query);
        $anyWord = Str::matchAll('/[\p{L}\p{N}]+/u', $query)->unique()->implode(' | ');

        $keywords = $this->candidates($context, $includeArchived, $except)
            ->select('id')
            ->selectRaw('ts_rank(to_tsvector(?::regconfig, content), to_tsquery(?::regconfig, ?)) as score', [$language, $language, $anyWord])
            ->whereFullText('content', $anyWord, ['language' => $language, 'mode' => 'raw'])
            ->when($anyWord === '', fn (Builder $query) => $query->whereRaw('false'))
            ->orderByDesc('score')
            ->limit(self::CANDIDATES)
            ->toBase()
            ->get()
            ->map(fn (object $row): array => ['id' => (int) $row->id, 'score' => -(float) $row->score]);

        $meanings = $this->candidates($context, $includeArchived, $except)
            ->select('id')
            ->selectVectorDistance('embedding', $vector, 'score')
            ->whereVectorSimilarTo('embedding', $vector, config('memory.recall_min_similarity'))
            ->limit(self::CANDIDATES)
            ->toBase()
            ->get()
            ->map(fn (object $row): array => ['id' => (int) $row->id, 'score' => (float) $row->score]);

        $scores = collect([$keywords, $meanings])
            ->flatMap(fn (BaseCollection $ranking): BaseCollection => $this->denseRanks($ranking))
            ->groupBy('id')
            ->map(fn (BaseCollection $hits): float => (float) $hits->sum('score'));

        return AgentMemory::query()
            ->whereKey($scores->keys())
            ->get()
            ->sort(fn (AgentMemory $a, AgentMemory $b): int => [$scores[$b->id], $b->strength()] <=> [$scores[$a->id], $a->strength()])
            ->take($limit)
            ->values();
    }

    /**
     * Turn hits ordered best first into fused scores, where hits with an equal score share a rank.
     *
     * @param  BaseCollection<int, array{id: int, score: float}>  $ranking
     * @return BaseCollection<int, array{id: int, score: float}>
     */
    protected function denseRanks(BaseCollection $ranking): BaseCollection
    {
        $ranks = $ranking->map(fn (array $hit): string => number_format($hit['score'], 6))->unique()->values()->flip();

        return $ranking->map(fn (array $hit): array => ['id' => $hit['id'], 'score' => 1.0 / (self::RRF_K + (int) $ranks[number_format($hit['score'], 6)] + 1)]);
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
