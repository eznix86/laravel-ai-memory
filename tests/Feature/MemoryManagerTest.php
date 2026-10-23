<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\Exceptions\RejectedMemory;
use Eznix86\AI\Memory\Facades\Memories;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Policies\MemoryPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

test('list returns live memories in the context, filtered by kind, subject and text', function (): void {
    $context = new MemoryContext(workspace(), user());

    memory($context, 'Customer 42 pays late.', MemoryKind::CustomerNote, ['subject' => 'customer:42']);
    memory($context, 'Customer 7 pays early.', MemoryKind::CustomerNote, ['subject' => 'customer:7']);
    memory($context, 'Deploys happen on Tuesday.', MemoryKind::Process);
    memory($context, 'Archived.', attributes: ['archived_at' => now()]);

    expect(Memories::list($context)->pluck('content'))->toHaveCount(3)
        ->and(Memories::list($context, kind: MemoryKind::Process)->pluck('content')->all())->toBe(['Deploys happen on Tuesday.'])
        ->and(Memories::list($context, subject: 'customer:42')->pluck('content')->all())->toBe(['Customer 42 pays late.'])
        ->and(Memories::list($context, search: 'early')->pluck('content')->all())->toBe(['Customer 7 pays early.']);
});

test('a fact added by hand records the user as source and creator', function (): void {
    $user = user();

    $memory = Memories::add(new MemoryContext(workspace(), $user), $user, ['kind' => 'process', 'content' => 'Deploys happen on Tuesday.']);

    expect($memory->source)->toBe('user')
        ->and($memory->creator->is($user))->toBeTrue();
});

test('a fact added by hand can be personal whatever its kind', function (): void {
    $user = user();

    $memory = Memories::add(new MemoryContext(workspace(), $user), $user, ['kind' => 'lesson', 'content' => 'I learn best from examples.', 'personal' => true]);

    expect($memory->user->is($user))->toBeTrue();
});

test('editing a memory changes its content and marks it as confirmed by the editor', function (): void {
    $context = new MemoryContext(workspace(), user());
    $memory = memory($context, 'Deploys happen on Tuesday.');
    $editor = user('Bob');

    Memories::edit($memory, 'Deploys happen on Thursday.', $editor);

    expect($memory->fresh())
        ->content->toBe('Deploys happen on Thursday.')
        ->creator->is($editor)->toBeTrue();
});

test('editing a memory goes through the same validation as writing', function (): void {
    $memory = memory(new MemoryContext(workspace()), 'Deploys happen on Tuesday.');

    Memories::edit($memory, 'The deploy password is hunter2.', user());
})->throws(RejectedMemory::class, 'a password');

test('forgetting archives the row, only deleting removes it', function (): void {
    $memory = memory(new MemoryContext(workspace()), 'Deploys happen on Tuesday.');

    $memory->update(['archived_at' => now()]);
    expect(AgentMemory::find($memory->id))->not->toBeNull();

    Memories::delete($memory);
    expect(AgentMemory::find($memory->id))->toBeNull();
});

test('export holds every memory of the user and nothing else', function (): void {
    $workspace = workspace();
    $ada = user('Ada');
    $context = new MemoryContext($workspace, $ada);

    $live = memory($context, 'Ada prefers tea.', MemoryKind::Preference);
    $archived = memory($context, 'Ada prefers coffee.', MemoryKind::Preference, ['archived_at' => now()]);
    memory($context, 'Shared lesson.');
    memory(new MemoryContext($workspace, user('Bob')), 'Bob prefers water.', MemoryKind::Preference);

    $export = Memories::export($ada);

    expect(array_column($export, 'id'))->toBe([$live->id, $archived->id])
        ->and($export[0])->not->toHaveKey('embedding');
});

test('privacy purge removes live, archived and superseded rows for the subject only', function (): void {
    $workspace = workspace();
    $context = new MemoryContext($workspace);

    $newer = memory($context, 'Customer 42 pays on time now.', MemoryKind::CustomerNote, ['subject' => 'customer:42']);
    memory($context, 'Customer 42 pays late.', MemoryKind::CustomerNote, ['subject' => 'customer:42', 'superseded_by' => $newer->id]);
    memory($context, 'Customer 42 asked for a call.', MemoryKind::CustomerNote, ['subject' => 'customer:42', 'archived_at' => now()]);
    $kept = memory($context, 'Customer 7 pays early.', MemoryKind::CustomerNote, ['subject' => 'customer:7']);
    $elsewhere = memory(new MemoryContext(workspace('Globex')), 'Their customer 42 is another one.', MemoryKind::CustomerNote, ['subject' => 'customer:42']);

    expect(Memories::purge($workspace, 'customer:42'))->toBe(3)
        ->and(AgentMemory::pluck('id')->sort()->values()->all())->toBe([$kept->id, $elsewhere->id]);
});

test('a user manages their own memories but not a colleague one or a shared one', function (): void {
    $workspace = workspace();
    $ada = user('Ada');

    $own = memory(new MemoryContext($workspace, $ada), 'Ada prefers tea.', MemoryKind::Preference);
    $colleague = memory(new MemoryContext($workspace, user('Bob')), 'Bob prefers water.', MemoryKind::Preference);
    $shared = memory(new MemoryContext($workspace), 'Shared lesson.');

    expect(Gate::forUser($ada)->allows('update', $own))->toBeTrue()
        ->and(Gate::forUser($ada)->allows('delete', $own))->toBeTrue()
        ->and(Gate::forUser($ada)->allows('delete', $colleague))->toBeFalse()
        ->and(Gate::forUser($ada)->allows('delete', $shared))->toBeFalse();
});

test('a policy that grants administration lets the admin manage shared memories', function (): void {
    Gate::policy(AgentMemory::class, AdminMemoryPolicy::class);

    $shared = memory(new MemoryContext(workspace()), 'Shared lesson.');

    expect(Gate::forUser(user('Admin'))->allows('delete', $shared))->toBeTrue()
        ->and(Gate::forUser(user('Ada'))->allows('delete', $shared))->toBeFalse();
});

class AdminMemoryPolicy extends MemoryPolicy
{
    #[\Override]
    protected function administers(Model $user, Model $tenant): bool
    {
        return $user->name === 'Admin';
    }
}
