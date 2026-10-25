<?php

declare(strict_types=1);

use Tests\Evals\EvalTestCase;

test('a real conversation where the user says something about themselves leaves that fact', function (array $case): void {
    $context = $this->context();

    $this->extractFrom($context, $case['turns']);

    expect($this->memoriesOf($context))->not->toBeEmpty('Nothing was stored.');
    expect(fn (): string => $this->memoriesOf($context)->implode("\n"))
        ->prompt($this->userSide($case['turns']))
        ->toBeFactual($case['fact']);
})->with(EvalTestCase::cases('oasst2', 'stored'));

test('a real conversation with nothing about the user leaves no memory', function (array $case): void {
    $context = $this->context();

    $this->extractFrom($context, $case['turns']);

    expect($this->memoriesOf($context)->all())->toBe([]);
})->with(EvalTestCase::cases('oasst2', 'ignored'));
