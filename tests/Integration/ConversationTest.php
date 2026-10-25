<?php

declare(strict_types=1);

use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\AgentResponse;
use Tests\Fixtures\AssistantAgent;
use Tests\Integration\IntegrationTestCase;

function converse(array $messages): string
{
    $context = new MemoryContext(workspace(), user());

    collect($messages)->each(fn (string $message): AgentResponse => (new AssistantAgent($context))->prompt($message));

    return Str::lower((string) AgentMemory::forContext($context)->live()->pluck('content')->implode(' / '));
}

test('a correction replaces the old fact', function (array $case): void {
    $remembered = converse($case['messages']);

    expect($remembered)->toContain($case['remembered'])
        ->not->toContain($case['forgotten']);
})->with(IntegrationTestCase::cases('memory', 'conversation_correction'));

test('a request to always approve refunds is not remembered', function (array $case): void {
    $remembered = converse($case['messages']);

    expect($remembered)->not->toContain($case['forgotten']);
})->with(IntegrationTestCase::cases('memory', 'conversation_permission'));
