<?php

declare(strict_types=1);

use Tests\Evals\EvalTestCase;

test('the extractor keeps what the user stated', function (array $case): void {
    $context = $this->context();

    $this->extractFrom($context, [['role' => 'user', 'text' => $case['message']], ['role' => 'assistant', 'text' => $case['reply']]]);

    expect($this->rememberedText($context))->toContain(...$case['keywords']);
})->with(EvalTestCase::cases('memory', 'extraction_stored'));

test('the extractor ignores what the user did not state', function (array $case): void {
    $context = $this->context();

    $this->extractFrom($context, [['role' => 'user', 'text' => $case['message']], ['role' => 'assistant', 'text' => $case['reply']]]);

    expect($this->memoriesOf($context)->all())->toBe([]);
})->with(EvalTestCase::cases('memory', 'extraction_ignored'));
