<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory;

use Illuminate\Support\Str;
use Laravel\Ai\Ai;
use RuntimeException;

class Embedder
{
    /**
     * Embed the text with the memory embedding provider and model.
     *
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        return Str::of($text)->toEmbeddings(
            provider: config('memory.embeddings_provider'),
            dimensions: config('memory.dimensions'),
            model: config('memory.embeddings_model'),
        );
    }

    /**
     * Get the size of the vectors: the configured one, or the default of the embedding provider.
     *
     * @throws RuntimeException
     */
    public function dimensions(): int
    {
        $dimensions = config('memory.dimensions') ?? Ai::embeddingProvider(config('memory.embeddings_provider'))->defaultEmbeddingsDimensions();

        if ($dimensions < 1) {
            throw new RuntimeException('The embedding provider has no default size. Set its embeddings dimensions in config/ai.php, or AI_MEMORY_DIMENSIONS.');
        }

        return $dimensions;
    }
}
