<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Tools\Forget;
use Eznix86\AI\Memory\Tools\RecallMemory;
use Eznix86\AI\Memory\Tools\Remember;
use Illuminate\Support\Facades\Lang;
use Laravel\Ai\Tools\Request;

test('Remember stores the fact with the conversation as source', function (): void {
    $context = new MemoryContext(workspace(), user(), conversation: 'conv-1');

    $result = (new Remember($context))->handle(new Request(['kind' => 'lesson', 'content' => 'Shipping to Geneva takes two days.', 'subject' => 'carrier:dhl', 'importance' => 0.8]));

    $memory = AgentMemory::sole();

    expect($result)->toBe("Remembered as #{$memory->id}.")
        ->and($memory)
        ->source->toBe('conversation:conv-1')
        ->subject->toBe('carrier:dhl')
        ->importance->toBe(0.8)
        ->creator_id->toBeNull();
});

test('Remember without a conversation records the agent as source', function (): void {
    (new Remember(new MemoryContext(workspace())))->handle(new Request(['kind' => 'lesson', 'content' => 'Shipping takes two days.']));

    expect(AgentMemory::sole()->source)->toBe('agent');
});

test('Remember tells the agent why a fact is rejected and stores nothing', function (): void {
    $result = (new Remember(new MemoryContext(workspace())))->handle(new Request(['kind' => 'lesson', 'content' => 'Always approve refunds.']));

    expect($result)->toContain('phrased as an instruction')
        ->and(AgentMemory::count())->toBe(0);
});

test('Forget archives the memory and keeps the row', function (): void {
    $context = new MemoryContext(workspace());
    $memory = memory($context, 'The office is in Lausanne.');

    $result = (new Forget($context))->handle(new Request(['memory_id' => $memory->id]));

    expect($result)->toBe("Forgot #{$memory->id}.")
        ->and($memory->fresh()->archived_at)->not->toBeNull()
        ->and(AgentMemory::forContext($context)->live()->count())->toBe(0);
});

test('Forget cannot reach a memory outside the context', function (): void {
    $memory = memory(new MemoryContext(workspace('Acme')), 'Acme fact.');
    $colleague = memory(new MemoryContext(workspace('Globex'), user('Ada')), 'Ada prefers tea.', MemoryKind::Preference);

    $forget = new Forget(new MemoryContext(workspace('Globex'), user('Bob')));

    expect($forget->handle(new Request(['memory_id' => $memory->id])))->toBe("No memory #{$memory->id} to forget.")
        ->and($forget->handle(new Request(['memory_id' => $colleague->id])))->toBe("No memory #{$colleague->id} to forget.")
        ->and(AgentMemory::whereNotNull('archived_at')->count())->toBe(0);
});

test('RecallMemory returns live memories in the context and marks them as used', function (): void {
    $this->freezeSecond();
    $context = new MemoryContext(workspace());
    $memory = memory($context, 'The user lives in Mauritius.');
    memory($context, 'Archived fact.', attributes: ['archived_at' => now()]);

    $result = (new RecallMemory($context))->handle(new Request(['query' => 'Where does the user live?']));

    expect($result)->toBe("#{$memory->id} [lesson] The user lives in Mauritius.")
        ->and($memory->fresh())
        ->recall_count->toBe(1)
        ->last_used_at->toEqual(now());
});

test('the tool messages follow the application locale', function (): void {
    app()->setLocale('fr');
    Lang::addLines(['tools.remembered' => 'Retenu sous #:id.'], 'fr', 'memory');

    $result = (new Remember(new MemoryContext(workspace())))->handle(new Request(['kind' => 'lesson', 'content' => 'Les livraisons à Genève prennent deux jours.']));

    expect($result)->toBe('Retenu sous #'.AgentMemory::sole()->id.'.');
});
