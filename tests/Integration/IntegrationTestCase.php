<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    #[\Override]
    public static function setUpBeforeClass(): void
    {
        if (static::live()) {
            parent::setUpBeforeClass();
        }
    }

    #[\Override]
    protected function setUp(): void
    {
        if (! static::live()) {
            $this->markTestSkipped('Set AI_MEMORY_PROVIDER and AI_MEMORY_MODEL to run the live tests.');
        }

        parent::setUp();
    }

    /**
     * Load the cases of a group from a case file, named by id and marked when held out.
     *
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function cases(string $file, string $group): array
    {
        $cases = (new Filesystem)->json(__DIR__."/cases/{$file}.json")[$group];

        return collect($cases)->mapWithKeys(fn (array $case, int $index): array => [
            ($case['id'] ?? $case['fact'] ?? "#{$index}").(($case['holdout'] ?? false) ? ' (holdout)' : '') => [$case],
        ])->all();
    }

    protected static function live(): bool
    {
        return (bool) env('AI_MEMORY_PROVIDER');
    }

    #[\Override]
    protected function memoryConfig(): array
    {
        return [];
    }

    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $provider = (string) env('AI_MEMORY_PROVIDER');

        $app['config']->set('ai.default', $provider);
        $app['config']->set("ai.providers.{$provider}.models.text.default", env('AI_MEMORY_MODEL') ?: null);
    }
}
