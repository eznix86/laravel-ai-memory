<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Tools\RecallMemory;
use Laravel\Ai\Tools\Request;

function recall(MemoryContext $context, string $query, bool $includeArchived = false): string
{
    return (string) (new RecallMemory($context))->handle(new Request(['query' => $query, 'include_archived' => $includeArchived]));
}

test('a keyword match is found even when the meaning search misses it', function (): void {
    config(['memory.recall_min_similarity' => 0.99]);
    $context = new MemoryContext(workspace());
    $memory = memory($context, 'Invoices for Globex are sent by courier.');

    expect(recall($context, 'courier'))->toBe("#{$memory->id} [lesson] Invoices for Globex are sent by courier.");
});

test('a meaning match is found even without a shared keyword stem', function (): void {
    $context = new MemoryContext(workspace());
    $memory = memory($context, 'Deploys happen weekly.');

    expect(recall($context, 'lesson'))->toContain("#{$memory->id}");
});

test('a memory found by both searches ranks above one found by one', function (): void {
    $context = new MemoryContext(workspace());
    $one = memory($context, 'Payments are late.');
    $both = memory($context, 'Payments from Globex are late.');

    expect(recall($context, 'Globex payments'))->toStartWith("#{$both->id}")->toContain("#{$one->id}");
});

test('between equal matches, the stronger memory ranks first', function (): void {
    $context = new MemoryContext(workspace());
    $faded = memory($context, 'Globex pays by card.', MemoryKind::Context, ['created_at' => now()->subDays(60)]);
    $fresh = memory($context, 'Globex pays by card.', MemoryKind::Context);

    expect(recall($context, 'Globex card'))->toStartWith("#{$fresh->id}")->toContain("#{$faded->id}");
});

test('recall returns at most the configured number of memories', function (): void {
    $context = new MemoryContext(workspace());
    collect(range(1, 12))->each(fn (int $i): AgentMemory => memory($context, "Globex note {$i}."));

    expect(substr_count(recall($context, 'Globex note'), "\n") + 1)->toBe(8);
});

test('archived memories are searched only when asked', function (): void {
    $context = new MemoryContext(workspace());
    $archived = memory($context, 'Globex used to pay by cheque.', attributes: ['archived_at' => now()]);

    expect(recall($context, 'Globex cheque'))->toBe('No relevant memories found.')
        ->and(recall($context, 'Globex cheque', includeArchived: true))->toBe("#{$archived->id} [lesson] (archived) Globex used to pay by cheque.");
});
