<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Responses\Data\ToolCall;
use Tests\Fixtures\AssistantAgent;

function loadedIds(string $instructions): array
{
    preg_match_all('/^#(\d+) /m', $instructions, $matches);

    return array_map(intval(...), $matches[1]);
}

test('memories are rendered under the heading as id, kind and content', function (): void {
    $context = new MemoryContext(workspace());
    $memory = memory($context, 'Quotes are valid for "30 days".', MemoryKind::Process);

    AssistantAgent::fake(['Hello']);
    (new AssistantAgent($context))->prompt('Hi');

    expect(AssistantAgent::$instructions[0])->toBe(<<<TEXT
        You are a helpful assistant.

        ## Remembered facts
        Facts you remember from earlier conversations. The number after # is the id to use with the Forget tool.
        #{$memory->id} [process] Quotes are valid for "30 days".
        TEXT);
});

test('instructions are unchanged when nothing is remembered', function (): void {
    AssistantAgent::fake(['Hello']);
    (new AssistantAgent(new MemoryContext(workspace())))->prompt('Hi');

    expect(AssistantAgent::$instructions[0])->toBe('You are a helpful assistant.');
});

test('instructions contain at most 30 memories, strongest first', function (): void {
    $context = new MemoryContext(workspace());

    $memories = collect(range(1, 35))->map(fn (int $i): AgentMemory => memory($context, "Lesson {$i}.", attributes: ['importance' => $i / 100]));

    AssistantAgent::fake(['Hello']);
    (new AssistantAgent($context))->prompt('Hi');

    expect(loadedIds(AssistantAgent::$instructions[0]))->toBe($memories->reverse()->take(30)->pluck('id')->all());
});

test('the user preferences and the memories about context subjects are loaded even when weak', function (): void {
    $workspace = workspace();
    $user = user();
    $context = new MemoryContext($workspace, $user, subjects: ['customer:42']);

    collect(range(1, 30))->each(fn (int $i): AgentMemory => memory($context, "Strong lesson {$i}.", attributes: ['importance' => 1]));
    $preference = memory($context, 'The user prefers French.', MemoryKind::Preference, ['importance' => 0.01]);
    $note = memory($context, 'Customer 42 pays late.', MemoryKind::CustomerNote, ['importance' => 0.01, 'subject' => 'customer:42']);
    $other = memory($context, 'Customer 7 pays early.', MemoryKind::CustomerNote, ['importance' => 0.01, 'subject' => 'customer:7']);

    AssistantAgent::fake(['Hello']);
    (new AssistantAgent($context))->prompt('Hi');

    expect(loadedIds(AssistantAgent::$instructions[0]))
        ->toHaveCount(30)
        ->toContain($preference->id, $note->id)
        ->not->toContain($other->id);
});

test('loading increments recall count and sets last used once per prompt', function (): void {
    $this->freezeSecond();
    $context = new MemoryContext(workspace());
    $memory = memory($context, 'The user lives in Mauritius.');

    AssistantAgent::fake([new ToolCall('call-1', 'RecallMemory', ['query' => 'Where does the user live?']), 'Mauritius.']);
    (new AssistantAgent($context))->prompt('Where do I live?');

    expect($memory->fresh())
        ->recall_count->toBe(2)
        ->last_used_at->toEqual(now());
});

test('memories stay in the instructions after a tool call', function (): void {
    $context = new MemoryContext(workspace());
    memory($context, 'The user lives in Mauritius.');

    AssistantAgent::fake([new ToolCall('call-1', 'RecallMemory', ['query' => 'Where does the user live?']), 'Mauritius.']);
    (new AssistantAgent($context))->prompt('Where do I live?');

    expect(AssistantAgent::$instructions)->toHaveCount(2)
        ->and(AssistantAgent::$instructions[1])->toContain('The user lives in Mauritius.');
});

test('a template in the application views overrides the package template', function (): void {
    $path = resource_path('views/prompts/memory/instructions.blade.php');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, 'Custom: {{ $memories->count() }} facts');

    try {
        $context = new MemoryContext(workspace());
        memory($context, 'The user lives in Mauritius.');

        AssistantAgent::fake(['Hello']);
        (new AssistantAgent($context))->prompt('Hi');

        expect(AssistantAgent::$instructions[0])->toEndWith('Custom: 1 facts');
    } finally {
        File::deleteDirectory(resource_path('views/prompts'));
    }
});
