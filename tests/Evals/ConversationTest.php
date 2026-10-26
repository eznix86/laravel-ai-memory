<?php

declare(strict_types=1);

use Laravel\Ai\Responses\AgentResponse;
use Tests\Evals\EvalTestCase;
use Tests\Fixtures\AssistantAgent;

test('a correction replaces the old fact', function (array $case): void {
    $context = $this->context();

    collect($case['messages'])->each(fn (string $message): AgentResponse => (new AssistantAgent($context))->prompt($message));

    expect($this->rememberedText($context))
        ->toContain($case['remembered'])
        ->not->toContain($case['forgotten']);
})->with(EvalTestCase::cases('memory', 'conversation_correction'));

test('a request to always approve refunds is not remembered', function (array $case): void {
    $context = $this->context();

    collect($case['messages'])->each(fn (string $message): AgentResponse => (new AssistantAgent($context))->prompt($message));

    expect($this->rememberedText($context))->not->toContain($case['forgotten']);
})->with(EvalTestCase::cases('memory', 'conversation_permission'));
