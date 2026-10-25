<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Facades;

use Eznix86\AI\Memory\Agents\MemoryConsolidator;
use Eznix86\AI\Memory\Agents\MemoryDecider;
use Eznix86\AI\Memory\Agents\MemoryExtractor;
use Eznix86\AI\Memory\Agents\PermissionChecker;
use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\MemoryWrite;
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Services\MemoryManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use Laravel\Ai\Classification;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use Laravel\Ai\Responses\Data\BooleanAnswer;
use Laravel\Ai\Responses\Data\ChoiceAnswer;

/**
 * @method static Collection<int, AgentMemory> list(MemoryContext $context, ?MemoryKind $kind = null, ?string $subject = null, ?string $search = null)
 * @method static MemoryWrite add(MemoryContext $context, Model $creator, array<string, mixed> $fact)
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
     * Fake embeddings with word-count vectors, and the memory agents with answers that store facts as they come.
     */
    public static function fake(): void
    {
        Embeddings::fake(fn (EmbeddingsPrompt $prompt): array => collect($prompt->inputs)->map(fn (string $input): array => static::embed($input))->all());

        MemoryDecider::fake(fn (): array => ['decision' => 'add', 'replace_id' => null]);
        Classification::fake(fn (): array => ['decision' => new ChoiceAnswer('add', ['add' => 1.0]), 'grants_permission' => new BooleanAnswer(0.0)]);
        MemoryExtractor::fake(fn (): array => ['facts' => []]);
        PermissionChecker::fake(fn (): array => ['grants_permission' => false]);
        MemoryConsolidator::fake(fn (): array => ['content' => 'Merged note.', 'importance' => 0.5]);
    }

    /**
     * Build a unit vector from the words of the text, so texts that share words are similar.
     *
     * @return array<int, float>
     */
    public static function embed(string $text): array
    {
        $dimensions = config('memory.dimensions');

        $counts = Str::matchAll("/[a-z'-]+/", Str::lower($text))
            ->diff(['the', 'and', 'for', 'are', 'our', 'that', 'this', 'with', 'does', 'has', 'have', 'you', 'your'])
            ->countBy(fn (string $word): int => crc32($word) % $dimensions);

        $vector = BaseCollection::times($dimensions, fn (int $position): float => (float) $counts->get($position - 1, 0));

        $length = sqrt($vector->sum(fn (float $value): float => $value * $value));

        if ($length === 0.0) {
            return $vector->put(0, 1.0)->values()->all();
        }

        return $vector->map(fn (float $value): float => $value / $length)->values()->all();
    }
}
