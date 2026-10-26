<?php

declare(strict_types=1);

use Tests\Evals\EvalTestCase;

test('a weak memory that answers the question is loaded among many strong ones', function (array $case): void {
    $context = $this->context();
    $this->rememberAll($context, EvalTestCase::data('memory', 'loading_memories'));
    $target = $this->remember($context, $case['target']);

    $instructions = $this->instructionsFor($context, $case['question']);

    expect($instructions)->toContain("#{$target->id} ");
})->with(EvalTestCase::cases('memory', 'loading'));

test('a weak memory named only earlier in the conversation is loaded for a follow-up', function (array $case): void {
    $context = $this->context();
    $this->rememberAll($context, EvalTestCase::data('memory', 'loading_memories'));
    $target = $this->remember($context, $case['target']);
    [$earlier, $last] = [array_slice($case['conversation'], 0, -1), end($case['conversation'])];

    $instructions = $this->instructionsFor($context, $last, $earlier);

    expect($instructions)->toContain("#{$target->id} ");
})->with(EvalTestCase::cases('memory', 'loading'));
