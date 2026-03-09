<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Services;

use Eznix86\AI\Memory\Models\Memory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MemoryManager
{
    /**
     * Store a memory with automatic embedding generation.
     *
     * @param  array<string, mixed>  $context
     */
    public function store(string $content, array $context = []): Memory
    {
        $embedding = Str::of($content)->toEmbeddings();

        return Memory::create(array_merge($context, [
            'content' => $content,
            'embedding' => $embedding,
        ]));
    }

    /**
     * Recall memories using vector similarity and reranking.
     *
     * @param  array<string, mixed>  $context
     * @return Collection<int, Memory>
     */
    public function recall(string $query, array $context = [], ?int $limit = null): Collection
    {
        $limit ??= config('memory.recall_limit', 10);
        $threshold = config('memory.similarity_threshold', 0.5);
        $oversampleFactor = config('memory.recall_oversample_factor', 2);

        $queryEmbedding = Str::of($query)->toEmbeddings();
        $memoryQuery = $this->applyContext(Memory::query(), $context);
        $candidateLimit = $limit * $oversampleFactor;

        $memories = $this->supportsVectorSearch()
            ? $memoryQuery->whereVectorSimilarTo('embedding', $queryEmbedding, $threshold)
                ->limit($candidateLimit)
                ->get()
            : $this->recallWithoutVectorSearch($memoryQuery, $queryEmbedding, $threshold, $candidateLimit);

        if ($memories->isEmpty()) {
            return collect();
        }

        return $memories
            ->rerank('content', $query)
            ->take($limit);
    }

    /**
     * Get all memories with optional context filtering.
     *
     * @param  array<string, mixed>  $context
     * @return \Illuminate\Database\Eloquent\Collection<int, Memory>
     */
    public function all(array $context = [], int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return $this->applyContext(Memory::query(), $context)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Delete a specific memory.
     */
    public function forget(int $memoryId): bool
    {
        return Memory::find($memoryId)?->delete() ?? false;
    }

    /**
     * Delete all memories matching the given context.
     *
     * @param  array<string, mixed>  $context
     */
    public function forgetAll(array $context = []): int
    {
        return (int) $this->applyContext(Memory::query(), $context)->delete();
    }

    /**
     * Apply context filters to a query builder.
     *
     * @param  mixed  $query
     * @param  array<string, mixed>  $context
     */
    protected function applyContext($query, array $context): mixed
    {
        foreach ($context as $field => $value) {
            $query->where($field, $value);
        }

        return $query;
    }

    protected function supportsVectorSearch(): bool
    {
        return DB::connection()->getDriverName() !== 'sqlite';
    }

    /**
     * @param  array<float>  $queryEmbedding
     * @return Collection<int, Memory>
     */
    protected function recallWithoutVectorSearch(Builder $query, array $queryEmbedding, float $threshold, int $limit): Collection
    {
        return $query->get()
            ->map(fn (Memory $memory): array => [
                'memory' => $memory,
                'similarity' => $this->cosineSimilarity($queryEmbedding, $memory->embedding),
            ])
            ->filter(fn (array $candidate): bool => $candidate['similarity'] >= $threshold)
            ->sortByDesc('similarity')
            ->take($limit)
            ->pluck('memory')
            ->values();
    }

    /**
     * @param  array<float>  $left
     * @param  array<float>  $right
     */
    protected function cosineSimilarity(array $left, array $right): float
    {
        $dotProduct = 0.0;
        $leftMagnitude = 0.0;
        $rightMagnitude = 0.0;

        foreach ($left as $index => $value) {
            $rightValue = $right[$index] ?? 0.0;

            $dotProduct += $value * $rightValue;
            $leftMagnitude += $value ** 2;
            $rightMagnitude += $rightValue ** 2;
        }

        if ($leftMagnitude === 0.0 || $rightMagnitude === 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($leftMagnitude) * sqrt($rightMagnitude));
    }
}
