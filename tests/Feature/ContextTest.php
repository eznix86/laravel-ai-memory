<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Tools\RecallMemory;
use Laravel\Ai\Tools\Request;
use Tests\Fixtures\AssistantAgent;
use Tests\Fixtures\User;
use Tests\Fixtures\Workspace;

test('a memory from one workspace never appears in another workspace', function (): void {
    $user = user();
    $acme = new MemoryContext(user: $user, owner: workspace('Acme'));
    $globex = new MemoryContext(user: $user, owner: workspace('Globex'));

    memory($acme, 'Acme closes its books on the 25th.');

    AssistantAgent::fake(['Hello']);
    (new AssistantAgent($globex))->prompt('Hi');

    expect(AssistantAgent::$instructions[0])->not->toContain('Acme closes its books')
        ->and((new RecallMemory($globex))->handle(new Request(['query' => 'books'])))->toBe('No relevant memories found.')
        ->and((new RecallMemory($acme))->handle(new Request(['query' => 'books'])))->toContain('Acme closes its books');
});

test('a owner with the same key but another type is a different owner', function (): void {
    $workspace = workspace();
    $user = user();

    memory(new MemoryContext(owner: $workspace), 'Workspace lesson.');

    $impostor = (new User)->forceFill(['id' => (string) $workspace->getKey()]);

    expect(AgentMemory::forContext(new MemoryContext(user: $user, owner: $impostor))->count())->toBe(0);
});

test('a user preference is loaded for that user and not for a colleague', function (): void {
    $workspace = workspace();
    $ada = user('Ada');
    $bob = user('Bob');

    memory(new MemoryContext(user: $ada, owner: $workspace), 'Ada prefers answers in bullet points.', MemoryKind::Preference);

    AssistantAgent::fake(['Hello', 'Hello']);
    (new AssistantAgent(new MemoryContext(user: $ada, owner: $workspace)))->prompt('Hi');
    (new AssistantAgent(new MemoryContext(user: $bob, owner: $workspace)))->prompt('Hi');

    expect(AssistantAgent::$instructions[0])->toContain('Ada prefers answers in bullet points.')
        ->and(AssistantAgent::$instructions[1])->not->toContain('Ada prefers');
});

test('a context without a user sees only owner-wide memories', function (): void {
    $workspace = workspace();

    memory(new MemoryContext(user: user(), owner: $workspace), 'Personal.', MemoryKind::Preference);
    memory(new MemoryContext(owner: $workspace), 'Shared.');

    expect(AgentMemory::forContext(new MemoryContext(owner: $workspace))->pluck('content')->all())->toBe(['Shared.']);
});

test('active excludes expired, archived and superseded memories', function (): void {
    $context = new MemoryContext(owner: workspace());

    $kept = memory($context, 'Still true.', attributes: ['expires_at' => now()->addDay()]);
    memory($context, 'Expired.', attributes: ['expires_at' => now()->subMinute()]);
    memory($context, 'Archived.', attributes: ['archived_at' => now()]);
    memory($context, 'Superseded.', attributes: ['superseded_by' => $kept->id]);

    expect(AgentMemory::forContext($context)->active()->pluck('content')->all())->toBe(['Still true.']);
});

test('without an owner, the user owns the memories and they stay private', function (): void {
    $ada = user('Ada');
    $bob = user('Bob');

    memory(new MemoryContext(user: $ada), 'Ada ships the release notes on Fridays.');

    expect(AgentMemory::forContext(new MemoryContext(user: $ada))->pluck('content')->all())->toBe(['Ada ships the release notes on Fridays.'])
        ->and(AgentMemory::forContext(new MemoryContext(user: $bob))->count())->toBe(0)
        ->and(AgentMemory::sole()->owner->is($ada))->toBeTrue();
});

test('a context needs a user or an owner', function (): void {
    new MemoryContext;
})->throws(InvalidArgumentException::class, 'needs a user, an owner, or both');

test('a conversation can be given as its id or as a model', function (): void {
    $user = user();
    $thread = Workspace::create(['name' => 'Thread']);

    expect(new MemoryContext(user: $user, conversation: 'conv-1'))->source()->toBe('conversation:conv-1')
        ->and(new MemoryContext(user: $user, conversation: $thread))->source()->toBe("conversation:{$thread->id}")
        ->and(new MemoryContext(user: $user))->source()->toBe('agent');
});
