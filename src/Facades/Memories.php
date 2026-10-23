<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Facades;

use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Services\MemoryManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;

/**
 * @method static Collection<int, AgentMemory> list(MemoryContext $context, ?MemoryKind $kind = null, ?string $subject = null, ?string $search = null)
 * @method static AgentMemory add(MemoryContext $context, Model $creator, array<string, mixed> $fact)
 * @method static AgentMemory edit(AgentMemory $memory, string $content, Model $editor)
 * @method static void delete(AgentMemory $memory)
 * @method static array<int, array<string, mixed>> export(Model $user)
 * @method static int purge(Model $tenant, string $subject)
 *
 * @see MemoryManager
 */
class Memories extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return MemoryManager::class;
    }

    /**
     * Fake embeddings with one identical unit vector, so every memory matches every query.
     */
    public static function fake(): void
    {
        $dimensions = config('memory.dimensions');

        $embedding = array_fill(0, $dimensions, 1.0 / sqrt($dimensions));

        Embeddings::fake(fn (EmbeddingsPrompt $prompt): array => array_map(fn (): array => $embedding, $prompt->inputs));
    }
}
