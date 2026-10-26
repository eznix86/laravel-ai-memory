<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Enums\WriteOutcome;
use Eznix86\AI\Memory\Services\MemoryWriter;
use Tests\Evals\EvalTestCase;

test('a fact about something else is added next to the close memory', function (array $case): void {
    $context = $this->context();
    $existing = $this->remember($context, $case['existing']);

    $write = app(MemoryWriter::class)->write($context, [...$case['new'], 'source' => 'eval']);

    expect($write->outcome)->toBe(WriteOutcome::Added)
        ->and($existing->fresh()->superseded_by)->toBeNull();
})->with(EvalTestCase::cases('memory', 'decider_add'));

test('a fact that contradicts the close memory replaces it', function (array $case): void {
    $context = $this->context();
    $existing = $this->remember($context, $case['existing']);

    $write = app(MemoryWriter::class)->write($context, [...$case['new'], 'source' => 'eval']);

    expect($write->outcome)->toBe(WriteOutcome::Updated)
        ->and($existing->fresh()->superseded_by)->toBe($write->memory->id);
})->with(EvalTestCase::cases('memory', 'decider_update'));

test('a fact that the close memory already says is not stored again', function (array $case): void {
    $context = $this->context();
    $existing = $this->remember($context, $case['existing']);

    $write = app(MemoryWriter::class)->write($context, [...$case['new'], 'source' => 'eval']);

    expect($write->outcome)->toBe(WriteOutcome::Known)
        ->and($write->memory->id)->toBe($existing->id);
})->with(EvalTestCase::cases('memory', 'decider_noop'));
