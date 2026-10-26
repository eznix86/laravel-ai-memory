<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Facades\Memories;
use Eznix86\AI\Memory\MemoryContext;
use Laravel\Ai\Responses\Data\ToolCall;
use Tests\Fixtures\AssistantAgent;

test('the documented example: the agent answers with what it remembers', function (): void {
    $context = new MemoryContext(user: $user = user());
    Memories::add($context, ['kind' => 'preference', 'content' => 'The user wants the weekly report as PDF.'], creator: $user);

    expect(new AssistantAgent($context))
        ->prompt('In which format should you send me the weekly report?')
        ->toSatisfy('The answer says the report is sent as a PDF.');
});

test('the documented example: a fact told in one conversation is known in the next one', function (): void {
    $context = new MemoryContext(user: user());

    (new AssistantAgent($context))->prompt('I live in Mauritius. What is a nice place for a weekend walk?');

    expect(new AssistantAgent($context))
        ->prompt('Which country do I live in?')
        ->toContain('Mauritius');
});

test('the documented example: the agent stores a preference when asked', function (): void {
    $context = new MemoryContext(user: user());

    $toolCalls = fn (string $message): string => json_encode(
        collect((new AssistantAgent($context))->prompt($message)->toolCalls)
            ->map(fn (ToolCall $call): array => ['name' => $call->name, 'arguments' => $call->arguments])
            ->values(),
    );

    expect($toolCalls)
        ->prompt('Remember that I prefer short answers.')
        ->toHaveToolCalls(['Remember' => fn (array $arguments): bool => $arguments['kind'] === 'preference']);
});

test('the documented example: a task with nothing about the user leaves no memory', function (): void {
    $context = new MemoryContext(user: user());

    (new AssistantAgent($context))->prompt('Write a haiku about autumn.');

    expect(Memories::list($context))->toBeEmpty();
});
