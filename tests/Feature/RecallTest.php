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

test('a memory is found by the meaning of its kind and content', function (): void {
    $context = new MemoryContext(owner: workspace());
    $memory = memory($context, 'Deploys happen weekly.');

    expect(recall($context, 'lesson'))->toContain("#{$memory->id}");
});

test('the memory closest in meaning ranks first', function (): void {
    $context = new MemoryContext(owner: workspace());
    $one = memory($context, 'Payments are late.');
    $both = memory($context, 'Payments from Globex are late.');

    expect(recall($context, 'Globex payments'))->toStartWith("#{$both->id}")->toContain("#{$one->id}");
});

test('between equal matches, the stronger memory ranks first', function (): void {
    $context = new MemoryContext(owner: workspace());
    $faded = memory($context, 'Globex pays by card.', MemoryKind::Context, ['created_at' => now()->subDays(60)]);
    $fresh = memory($context, 'Globex pays by card.', MemoryKind::Context);

    expect(recall($context, 'Globex card'))->toStartWith("#{$fresh->id}")->toContain("#{$faded->id}");
});

test('recall returns at most the configured number of memories', function (): void {
    $context = new MemoryContext(owner: workspace());
    collect(range(1, 12))->each(fn (int $i): AgentMemory => memory($context, "Globex note {$i}."));

    expect(substr_count(recall($context, 'Globex note'), "\n") + 1)->toBe(8);
});

test('archived memories are searched only when asked', function (): void {
    $context = new MemoryContext(owner: workspace());
    $archived = memory($context, 'Globex used to pay by cheque.', attributes: ['archived_at' => now()]);

    expect(recall($context, 'Globex cheque'))->toBe('No relevant memories found.')
        ->and(recall($context, 'Globex cheque', includeArchived: true))->toBe("#{$archived->id} [lesson] (archived) Globex used to pay by cheque.");
});

test('a long question finds a memory that shares only some of its words', function (): void {
    $context = new MemoryContext(owner: workspace());
    $memory = memory($context, 'Invoices for Globex are sent by courier.');

    $found = (new RecallMemory($context))->search('Globex called this morning about their invoices, can you check how we send them?');

    expect($found->pluck('id')->all())->toBe([$memory->id]);
});

test('meaning ranks first, and strength only breaks ties', function (): void {
    $context = new MemoryContext(owner: workspace());
    $exact = memory($context, 'Customer 42 wants invoices in German.', MemoryKind::CustomerNote, ['importance' => 0.05, 'created_at' => now()->subDays(200)]);
    $partial = memory($context, 'Customer 7 wants invoices in French.', MemoryKind::CustomerNote, ['importance' => 1]);

    $found = (new RecallMemory($context))->search('Which language for the invoices of customer 42, German?');

    expect($found->pluck('id')->all())->toBe([$exact->id, $partial->id]);
});

test('a memory below the minimum similarity is not found', function (): void {
    config(['memory.recall_min_similarity' => 0.5]);
    $context = new MemoryContext(owner: workspace());
    memory($context, 'Invoices for Globex are sent by courier.');

    expect(recall($context, 'When are Globex payments due?'))->toBe('No relevant memories found.');
});
