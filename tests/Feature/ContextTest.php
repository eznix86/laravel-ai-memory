<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Tools\RecallMemory;
use Laravel\Ai\Tools\Request;
use Tests\Fixtures\AssistantAgent;
use Tests\Fixtures\User;

test('a memory from one workspace never appears in another workspace', function (): void {
    $user = user();
    $acme = new MemoryContext(workspace('Acme'), $user);
    $globex = new MemoryContext(workspace('Globex'), $user);

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

    memory(new MemoryContext($workspace), 'Workspace lesson.');

    $impostor = (new User)->forceFill(['id' => (string) $workspace->getKey()]);

    expect(AgentMemory::forContext(new MemoryContext($impostor, $user))->count())->toBe(0);
});

test('a user preference is loaded for that user and not for a colleague', function (): void {
    $workspace = workspace();
    $ada = user('Ada');
    $bob = user('Bob');

    memory(new MemoryContext($workspace, $ada), 'Ada prefers answers in bullet points.', MemoryKind::Preference);

    AssistantAgent::fake(['Hello', 'Hello']);
    (new AssistantAgent(new MemoryContext($workspace, $ada)))->prompt('Hi');
    (new AssistantAgent(new MemoryContext($workspace, $bob)))->prompt('Hi');

    expect(AssistantAgent::$instructions[0])->toContain('Ada prefers answers in bullet points.')
        ->and(AssistantAgent::$instructions[1])->not->toContain('Ada prefers');
});

test('a context without a user sees only owner-wide memories', function (): void {
    $workspace = workspace();

    memory(new MemoryContext($workspace, user()), 'Personal.', MemoryKind::Preference);
    memory(new MemoryContext($workspace), 'Shared.');

    expect(AgentMemory::forContext(new MemoryContext($workspace))->pluck('content')->all())->toBe(['Shared.']);
});

test('live excludes expired, archived and superseded memories', function (): void {
    $context = new MemoryContext(workspace());

    $kept = memory($context, 'Still true.', attributes: ['expires_at' => now()->addDay()]);
    memory($context, 'Expired.', attributes: ['expires_at' => now()->subMinute()]);
    memory($context, 'Archived.', attributes: ['archived_at' => now()]);
    memory($context, 'Superseded.', attributes: ['superseded_by' => $kept->id]);

    expect(AgentMemory::forContext($context)->live()->pluck('content')->all())->toBe(['Still true.']);
});
