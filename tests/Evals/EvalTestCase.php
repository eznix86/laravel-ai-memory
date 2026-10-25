<?php

declare(strict_types=1);

namespace Tests\Evals;

use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\Jobs\ExtractMemories;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Ai\Messages\UserMessage;
use Pest\Evals\Plugin;
use Tests\Fixtures\AssistantAgent;
use Tests\TestCase;

abstract class EvalTestCase extends TestCase
{
    #[\Override]
    public static function setUpBeforeClass(): void
    {
        if (static::live()) {
            parent::setUpBeforeClass();
        }
    }

    #[\Override]
    protected function setUp(): void
    {
        if (! static::live()) {
            $this->markTestSkipped('Eval skipped. Run with [--evals] to evaluate against a real model.');
        }

        parent::setUp();
    }

    /**
     * Load the cases of a group from a case file, named by id and marked when held out.
     *
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function cases(string $file, string $group): array
    {
        return collect(self::data($file, $group))->mapWithKeys(fn (array $case, int $index): array => [
            ($case['id'] ?? $case['fact'] ?? "#{$index}").(($case['holdout'] ?? false) ? ' (holdout)' : '') => [$case],
        ])->all();
    }

    /**
     * A context with a fresh user who belongs to a fresh workspace.
     */
    protected function context(): MemoryContext
    {
        return new MemoryContext(user: user(), owner: workspace());
    }

    /**
     * Store one memory of a case as it is, without the writer, so the test starts from exactly that row.
     *
     * @param  array{kind: string, content: string, subject?: string|null, importance?: float, days_unused?: int}  $memory
     */
    protected function remember(MemoryContext $context, array $memory): AgentMemory
    {
        return seed($context, $memory['content'], MemoryKind::from($memory['kind']), array_filter([
            'subject' => $memory['subject'] ?? null,
            'importance' => $memory['importance'] ?? null,
            'last_used_at' => isset($memory['days_unused']) ? now()->subDays($memory['days_unused']) : null,
            'created_at' => isset($memory['days_unused']) ? now()->subDays($memory['days_unused']) : null,
        ], fn (mixed $value): bool => $value !== null));
    }

    /**
     * @param  array<int, array{kind: string, content: string, subject?: string|null, importance?: float, days_unused?: int}>  $memories
     * @return Collection<int, AgentMemory>
     */
    protected function rememberAll(MemoryContext $context, array $memories): Collection
    {
        return collect($memories)->map(fn (array $memory): AgentMemory => $this->remember($context, $memory));
    }

    /**
     * Run the extraction job on every turn of a conversation, as an application would after each prompt.
     *
     * @param  array<int, array{role: string, text: string}>  $turns
     */
    protected function extractFrom(MemoryContext $context, array $turns): void
    {
        collect($turns)->chunk(2)->each(fn (Collection $turn) => ExtractMemories::dispatchSync(
            $context->owner, $context->user, null, $turn->first()['text'], (string) ($turn->skip(1)->first()['text'] ?? ''),
        ));
    }

    /**
     * @return Collection<int, string>
     */
    protected function memoriesOf(MemoryContext $context): Collection
    {
        return AgentMemory::forContext($context)->live()->pluck('content');
    }

    /**
     * The live memories as one lowercased text, for keyword checks.
     */
    protected function rememberedText(MemoryContext $context): string
    {
        return Str::lower($this->memoriesOf($context)->implode(' / '));
    }

    /**
     * Prompt the test agent, with a faked answer, and return the instructions it was given.
     *
     * @param  array<int, string>  $earlierMessages
     */
    protected function instructionsFor(MemoryContext $context, string $message, array $earlierMessages = []): string
    {
        AssistantAgent::$history = collect($earlierMessages)->map(fn (string $text): UserMessage => new UserMessage($text))->all();
        AssistantAgent::fake(['Done.']);

        (new AssistantAgent($context))->prompt($message);

        return (string) AssistantAgent::$instructions[0];
    }

    /**
     * @param  array<int, array{role: string, text: string}>  $turns
     */
    protected function userSide(array $turns): string
    {
        return collect($turns)->where('role', 'user')->pluck('text')->implode("\n");
    }

    /**
     * Load the raw rows of a group from a case file.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function data(string $file, string $group): array
    {
        return (new Filesystem)->json(__DIR__."/cases/{$file}.json")[$group];
    }

    protected static function live(): bool
    {
        return Plugin::isEvalMode();
    }

    #[\Override]
    protected function memoryConfig(): array
    {
        return [];
    }

    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $provider = env('AI_MEMORY_PROVIDER') ?: config('ai.default');

        $app['config']->set('ai.default', $provider);
        $app['config']->set("ai.providers.{$provider}.models.text.default", env('AI_MEMORY_MODEL') ?: null);
    }
}
