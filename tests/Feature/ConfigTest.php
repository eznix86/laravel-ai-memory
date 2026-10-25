<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Agents\MemoryConsolidator;
use Eznix86\AI\Memory\Agents\MemoryDecider;
use Eznix86\AI\Memory\Agents\MemoryExtractor;
use Eznix86\AI\Memory\Agents\PermissionChecker;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\Lab;
use Tests\TestCase;

test('the feature tests run on the defaults of the config file', function (): void {
    $saved = collect([...$_ENV, ...$_SERVER])->filter(fn (mixed $value, string $key): bool => Str::startsWith($key, 'AI_MEMORY_'));
    $saved->keys()->each(function (string $key): void {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    });

    try {
        $defaults = require __DIR__.'/../../config/memory.php';
    } finally {
        $saved->each(function (mixed $value, string $key): void {
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        });
    }

    expect(Arr::only($defaults, array_keys(TestCase::MEMORY_DEFAULTS)))->toBe(TestCase::MEMORY_DEFAULTS);
});

test('empty provider and model settings fall back to the application defaults', function (): void {
    putenv('AI_MEMORY_PROVIDER=');
    putenv('AI_MEMORY_MODEL=');

    try {
        $config = require __DIR__.'/../../config/memory.php';

        expect($config['provider'])->toBeNull()
            ->and($config['model'])->toBeNull();
    } finally {
        putenv('AI_MEMORY_PROVIDER');
        putenv('AI_MEMORY_MODEL');
    }
});

test('the memory agents send the options configured for their provider only', function (): void {
    config(['memory.provider_options' => ['openrouter' => ['provider' => ['only' => ['modelrun/fp4']]]]]);

    $agents = [new MemoryDecider, new MemoryExtractor, new MemoryConsolidator, new PermissionChecker];

    expect(collect($agents)->map->providerOptions(Lab::OpenRouter)->unique()->all())->toBe([['provider' => ['only' => ['modelrun/fp4']]]])
        ->and(collect($agents)->map->providerOptions('ollama')->unique()->all())->toBe([[]]);
});
