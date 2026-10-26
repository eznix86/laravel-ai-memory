<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\Facades\Memories;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Fixtures\User;
use Tests\Fixtures\Workspace;

function versionOneTable(array $rows): void
{
    Schema::create('memories', function (Blueprint $table): void {
        $table->id();
        $table->string('user_id')->nullable();
        $table->text('content');
        $table->timestamps();
    });

    DB::table('memories')->insert($rows);
}

test('the memories of version 1 are copied for their users, and the rows that cannot be are reported', function (): void {
    Memories::fake();
    $user = user();
    versionOneTable([
        ['user_id' => $user->id, 'content' => 'The user prefers dark mode.', 'created_at' => '2025-03-01 10:00:00', 'updated_at' => '2025-03-01 10:00:00'],
        ['user_id' => null, 'content' => 'Nobody said this.', 'created_at' => null, 'updated_at' => null],
        ['user_id' => (string) Str::uuid(), 'content' => 'The user lives in Oslo.', 'created_at' => null, 'updated_at' => null],
        ['user_id' => 'guest', 'content' => 'The user likes tea.', 'created_at' => null, 'updated_at' => null],
        ['user_id' => $user->id, 'content' => str_repeat('A very long note. ', 40), 'created_at' => null, 'updated_at' => null],
    ]);

    $this->artisan('memory:import-v1', ['--user-model' => User::class])
        ->expectsTable(['Result', 'Rows'], [
            ['added', 1],
            ['rejected: '.__('memory::rejections.invalid', ['reason' => __('validation.max.string', ['attribute' => 'content', 'max' => 300])]), 1],
            ['skipped: no user', 1],
            ['skipped: user not found', 2],
        ])
        ->assertSuccessful();

    expect(AgentMemory::sole())
        ->content->toBe('The user prefers dark mode.')
        ->kind->toBe(MemoryKind::Profile)
        ->source->toBe('v1:1')
        ->created_at->toDateTimeString()->toBe('2025-03-01 10:00:00')
        ->and(Memories::list(new MemoryContext(user: $user))->pluck('content')->all())->toBe(['The user prefers dark mode.']);
});

test('running the import again copies nothing twice', function (): void {
    Memories::fake();
    versionOneTable([['user_id' => user()->id, 'content' => 'The user prefers dark mode.', 'created_at' => null, 'updated_at' => null]]);

    $this->artisan('memory:import-v1', ['--user-model' => User::class])->assertSuccessful();
    $this->artisan('memory:import-v1', ['--user-model' => User::class])
        ->expectsTable(['Result', 'Rows'], [['skipped: already imported', 1]])
        ->assertSuccessful();

    expect(AgentMemory::count())->toBe(1);
});

test('a dry run counts the rows and writes nothing', function (): void {
    Memories::fake();
    versionOneTable([['user_id' => user()->id, 'content' => 'The user prefers dark mode.', 'created_at' => null, 'updated_at' => null]]);

    $this->artisan('memory:import-v1', ['--user-model' => User::class, '--dry-run' => true])
        ->expectsTable(['Result', 'Rows'], [['would be imported', 1]])
        ->assertSuccessful();

    expect(AgentMemory::count())->toBe(0);
});

test('the import stops when the old table or the user model is wrong', function (): void {
    $this->artisan('memory:import-v1', ['--user-model' => User::class])->assertFailed();

    versionOneTable([]);

    $this->artisan('memory:import-v1', ['--user-model' => 'App\\Missing'])->assertFailed();
    $this->artisan('memory:import-v1', ['--user-model' => User::class, '--kind' => 'gossip'])->assertFailed();
});

test('an old user id that cannot be an integer key is skipped, not a failed query', function (): void {
    Memories::fake();
    $owner = workspace();
    versionOneTable([
        ['user_id' => (string) $owner->id, 'content' => 'The user prefers dark mode.', 'created_at' => null, 'updated_at' => null],
        ['user_id' => 'guest', 'content' => 'The user likes tea.', 'created_at' => null, 'updated_at' => null],
    ]);

    $this->artisan('memory:import-v1', ['--user-model' => Workspace::class])
        ->expectsTable(['Result', 'Rows'], [['added', 1], ['skipped: user not found', 1]])
        ->assertSuccessful();
});
