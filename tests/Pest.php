<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Services\MemoryWriter;
use Tests\Fixtures\User;
use Tests\Fixtures\Workspace;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function workspace(string $name = 'Acme'): Workspace
{
    return Workspace::create(['name' => $name]);
}

function user(string $name = 'Ada'): User
{
    return User::create(['name' => $name]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function memory(MemoryContext $context, string $content, MemoryKind $kind = MemoryKind::Lesson, array $attributes = []): AgentMemory
{
    $memory = app(MemoryWriter::class)->write($context, ['kind' => $kind, 'content' => $content, 'source' => 'test']);

    if ($attributes !== []) {
        $memory->forceFill($attributes)->saveQuietly();
    }

    return $memory;
}
