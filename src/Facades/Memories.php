<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Facades;

use Eznix86\AI\Memory\Agents\MemoryConsolidator;
use Eznix86\AI\Memory\Agents\MemoryDecider;
use Eznix86\AI\Memory\Agents\MemoryExtractor;
use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\MemoryWrite;
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Services\MemoryManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Laravel\Ai\Classification;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
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
        Embeddings::fake(fn (EmbeddingsPrompt $prompt): array => array_map(static::embed(...), $prompt->inputs));

        MemoryDecider::fake(fn (): array => ['decision' => 'add', 'replace_id' => null]);
        Classification::fake(fn (): array => ['decision' => new ChoiceAnswer('add', ['add' => 1.0])]);
        MemoryExtractor::fake(fn (): array => ['facts' => []]);
        MemoryConsolidator::fake(fn (): array => ['content' => 'Merged note.', 'importance' => 0.5]);
    }

    /**
     * Build a unit vector from the words of the text, so texts that share words are similar.
     *
     * @return list<float>
     */
    public static function embed(string $text): array
    {
        $dimensions = config('memory.dimensions');

        $vector = array_fill(0, $dimensions, 0.0);

        $words = array_diff(str_word_count(strtolower($text), 1), ['the', 'and', 'for', 'are', 'our', 'that', 'this', 'with', 'does', 'has', 'have', 'you', 'your']);

        foreach ($words as $word) {
            $vector[crc32($word) % $dimensions] += 1.0;
        }

        $length = sqrt(array_sum(array_map(fn (float $value): float => $value * $value, $vector)));

        if ($length === 0.0) {
            $vector[0] = 1.0;

            return array_values($vector);
        }

        return array_values(array_map(fn (float $value): float => $value / $length, $vector));
    }
}
