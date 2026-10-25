<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Laravel\Ai\Messages\UserMessage;
use Tests\Fixtures\AssistantAgent;
use Tests\Integration\IntegrationTestCase;

function crowdedMemory(MemoryContext $context, array $target): AgentMemory
{
    collect(IntegrationTestCase::cases('memory', 'loading_memories'))->each(fn (array $memory): AgentMemory => seed(
        $context, $memory[0]['content'], MemoryKind::from($memory[0]['kind']), [
            'subject' => $memory[0]['subject'] ?? null,
            'importance' => $memory[0]['importance'],
            'last_used_at' => now()->subDays($memory[0]['days_unused']),
        ],
    ));

    return seed($context, $target['content'], MemoryKind::from($target['kind']), [
        'subject' => $target['subject'],
        'importance' => 0.2,
        'last_used_at' => now()->subDays(200),
        'created_at' => now()->subDays(200),
    ]);
}

test('a weak memory that answers the question is loaded among many strong ones', function (array $case): void {
    $context = new MemoryContext(user: user(), owner: workspace());
    $target = crowdedMemory($context, $case['target']);
    AssistantAgent::fake(['Done.']);

    (new AssistantAgent($context))->prompt($case['question']);

    expect(AssistantAgent::$instructions[0])->toContain("#{$target->id} ");
})->with(IntegrationTestCase::cases('memory', 'loading'));

test('a weak memory named only earlier in the conversation is loaded for a follow-up', function (array $case): void {
    $context = new MemoryContext(user: user(), owner: workspace());
    $target = crowdedMemory($context, $case['target']);
    AssistantAgent::$history = collect($case['conversation'])->slice(0, -1)->map(fn (string $text): UserMessage => new UserMessage($text))->values()->all();
    AssistantAgent::fake(['Done.']);

    (new AssistantAgent($context))->prompt(collect($case['conversation'])->last());

    expect(AssistantAgent::$instructions[0])->toContain("#{$target->id} ");
})->with(IntegrationTestCase::cases('memory', 'loading'));
