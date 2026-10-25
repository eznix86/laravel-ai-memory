<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\Enums\WriteOutcome;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Services\MemoryWriter;
use Tests\Integration\IntegrationTestCase;

function writeAfter(array $case): array
{
    $context = new MemoryContext(user: user(), owner: workspace());
    $existing = seed($context, $case['existing']['content'], MemoryKind::from($case['existing']['kind']), ['subject' => $case['existing']['subject'] ?? null]);

    return [$existing, app(MemoryWriter::class)->write($context, [...$case['new'], 'source' => 'test'])];
}

test('a fact about something else is added next to the close memory', function (array $case): void {
    [$existing, $write] = writeAfter($case);

    expect($write->outcome)->toBe(WriteOutcome::Added)
        ->and($existing->fresh()->superseded_by)->toBeNull();
})->with(IntegrationTestCase::cases('memory', 'decider_add'));

test('a fact that contradicts the close memory replaces it', function (array $case): void {
    [$existing, $write] = writeAfter($case);

    expect($write->outcome)->toBe(WriteOutcome::Updated)
        ->and($existing->fresh()->superseded_by)->toBe($write->memory->id);
})->with(IntegrationTestCase::cases('memory', 'decider_update'));

test('a fact that the close memory already says is not stored again', function (array $case): void {
    [$existing, $write] = writeAfter($case);

    expect($write->outcome)->toBe(WriteOutcome::Known)
        ->and($write->memory->id)->toBe($existing->id);
})->with(IntegrationTestCase::cases('memory', 'decider_noop'));
