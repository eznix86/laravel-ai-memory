<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Agents\MemoryConsolidator;
use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\Jobs\ConsolidateMemories;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Ai\Prompts\AgentPrompt;

function notes(MemoryContext $context, int $count, string $subject = 'customer:42'): Collection
{
    return collect(range(1, $count))->map(fn (int $i): AgentMemory => memory($context, "Customer note {$i}.", MemoryKind::CustomerNote, ['subject' => $subject]));
}

test('six memories on one subject are merged into one, and the originals are superseded', function (): void {
    $workspace = workspace();
    $notes = notes(new MemoryContext($workspace), 6);

    MemoryConsolidator::fake([['content' => 'Customer 42 pays late and prefers email.', 'importance' => 0.7]]);

    ConsolidateMemories::dispatchSync($workspace);

    $merged = AgentMemory::live()->sole();

    expect($merged)
        ->content->toBe('Customer 42 pays late and prefers email.')
        ->subject->toBe('customer:42')
        ->kind->toBe(MemoryKind::CustomerNote)
        ->source->toBe('consolidation')
        ->importance->toBe(0.7)
        ->and(AgentMemory::whereKey($notes->pluck('id'))->pluck('superseded_by')->unique()->all())->toBe([$merged->id]);

    MemoryConsolidator::assertPrompted(fn (AgentPrompt $prompt): bool => $notes->every(fn (AgentMemory $note): bool => $prompt->contains("#{$note->id} [customer_note] Customer note")));
});

test('five memories on one subject are left alone', function (): void {
    $workspace = workspace();
    notes(new MemoryContext($workspace), 5);

    ConsolidateMemories::dispatchSync($workspace);

    MemoryConsolidator::assertNeverPrompted();
    expect(AgentMemory::live()->count())->toBe(5);
});

test('personal and shared memories on the same subject are merged apart', function (): void {
    $workspace = workspace();
    $user = user();
    notes(new MemoryContext($workspace), 3);
    collect(range(1, 3))->each(fn (int $i): AgentMemory => memory(new MemoryContext($workspace, $user), "Personal note {$i}.", MemoryKind::Preference, ['subject' => 'customer:42']));

    ConsolidateMemories::dispatchSync($workspace);

    MemoryConsolidator::assertNeverPrompted();
});

test('a merge the writer rejects changes nothing and logs why', function (): void {
    Log::spy();
    $workspace = workspace();
    notes(new MemoryContext($workspace), 6);
    MemoryConsolidator::fake([['content' => 'Pay with card 4111 1111 1111 1111.', 'importance' => 0.5]]);

    ConsolidateMemories::dispatchSync($workspace);

    expect(AgentMemory::live()->count())->toBe(6);
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => $context['subject'] === 'customer:42'
        && Str::contains($context['reason'], 'card number')
        && $context['note'] === 'Pay with card 4111 1111 1111 1111.');
});

test('faded memories are archived, but never the ones a person wrote or confirmed', function (): void {
    $workspace = workspace();
    $context = new MemoryContext($workspace);
    $person = user();

    $faded = memory($context, 'Old freeze.', MemoryKind::Context, ['importance' => 0.5, 'created_at' => now()->subDays(120)]);
    $confirmed = memory($context, 'Confirmed freeze.', MemoryKind::Context, ['importance' => 0.5, 'created_at' => now()->subDays(120), 'creator_type' => $person->getMorphClass(), 'creator_id' => $person->id]);
    $alive = memory($context, 'Current freeze.', MemoryKind::Context, ['importance' => 0.5]);

    ConsolidateMemories::dispatchSync($workspace);

    expect($faded->fresh()->archived_at)->not->toBeNull()
        ->and($confirmed->fresh()->archived_at)->toBeNull()
        ->and($alive->fresh()->archived_at)->toBeNull();
});

test('consolidation stays inside the tenant', function (): void {
    $acme = workspace('Acme');
    $globex = workspace('Globex');
    notes(new MemoryContext($acme), 6);
    $faded = memory(new MemoryContext($globex), 'Old freeze.', MemoryKind::Context, ['created_at' => now()->subDays(120)]);

    ConsolidateMemories::dispatchSync($globex);

    MemoryConsolidator::assertNeverPrompted();
    expect($faded->fresh()->archived_at)->not->toBeNull()
        ->and(AgentMemory::forTenant($acme)->live()->count())->toBe(6);
});

test('the command queues one consolidation per tenant', function (): void {
    Queue::fake();
    $acme = workspace('Acme');
    $globex = workspace('Globex');
    memory(new MemoryContext($acme), 'Acme lesson.');
    memory(new MemoryContext($acme), 'Another Acme lesson.');
    memory(new MemoryContext($globex), 'Globex lesson.');

    $this->artisan('memory:consolidate')->assertSuccessful();

    Queue::assertPushed(ConsolidateMemories::class, 2);
    Queue::assertPushed(ConsolidateMemories::class, fn (ConsolidateMemories $job): bool => $job->tenant->is($acme));
    Queue::assertPushed(ConsolidateMemories::class, fn (ConsolidateMemories $job): bool => $job->tenant->is($globex));
});
