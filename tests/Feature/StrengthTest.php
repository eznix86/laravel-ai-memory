<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;

test('a context memory unused for 60 days ranks below a process memory of the same age', function (): void {
    $context = new MemoryContext(workspace());

    $temporary = memory($context, 'The team is in a release freeze.', MemoryKind::Context, ['created_at' => now()->subDays(60)]);
    $process = memory($context, 'Refunds need a manager.', MemoryKind::Process, ['created_at' => now()->subDays(60)]);

    expect($temporary->fresh()->strength())->toBeLessThan($process->fresh()->strength())
        ->and(AgentMemory::forContext($context)->strongestFirst()->pluck('id')->all())->toBe([$process->id, $temporary->id]);
});

test('strength halves after one half-life and grows with use, up to 1', function (): void {
    $this->freezeSecond();
    $context = new MemoryContext(workspace());

    $halved = memory($context, 'A lesson.', MemoryKind::Lesson, ['importance' => 0.8, 'last_used_at' => now()->subDays(90)]);
    $used = memory($context, 'A used lesson.', MemoryKind::Lesson, ['importance' => 0.5, 'recall_count' => 4]);
    $capped = memory($context, 'A strong lesson.', MemoryKind::Lesson, ['importance' => 1, 'recall_count' => 50]);

    expect($halved->fresh()->strength())->toEqualWithDelta(0.4, 0.0001)
        ->and($used->fresh()->strength())->toEqualWithDelta(0.7, 0.0001)
        ->and($capped->fresh()->strength())->toBe(1.0);
});

test('the SQL strength matches the PHP strength', function (): void {
    $this->freezeSecond();
    $context = new MemoryContext(workspace(), user());

    foreach (MemoryKind::cases() as $index => $kind) {
        memory($context, "A {$kind->value} fact.", $kind, [
            'importance' => 0.3 + $index / 10,
            'recall_count' => $index * 3,
            'last_used_at' => $index % 2 === 0 ? now()->subDays($index * 40) : null,
            'created_at' => now()->subDays($index * 25),
        ]);
    }

    [$expression, $bindings] = AgentMemory::strengthExpression();

    foreach (AgentMemory::query()->selectRaw("*, {$expression} as sql_strength", $bindings)->get() as $memory) {
        expect((float) $memory->sql_strength)->toEqualWithDelta($memory->strength(), 0.000001);
    }
});
