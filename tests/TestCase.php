<?php

declare(strict_types=1);

namespace Tests;

use Eznix86\AI\Memory\MemoryServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Testcontainers\Container\GenericContainer;
use Testcontainers\Container\StartedGenericContainer;
use Testcontainers\Wait\WaitForExec;
use Tests\Fixtures\AssistantAgent;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * The memory config the feature tests run on, whatever the environment says.
     *
     * @var array<string, mixed>
     */
    public const array MEMORY_DEFAULTS = [
        'table' => 'agent_memories',
        'embeddings_provider' => null,
        'embeddings_model' => null,
        'dimensions' => null,
        'fulltext_language' => 'english',
        'loaded_limit' => 30,
        'relevant_limit' => 8,
        'recall_limit' => 8,
        'recall_min_similarity' => 0.3,
        'provider' => null,
        'model' => null,
        'timeout' => 120,
        'provider_options' => [],
        'decider' => 'agent',
        'classification_provider' => null,
        'classification_model' => null,
        'permission_guard' => 'none',
        'extraction' => true,
        'queue' => null,
        'duplicate_similarity' => 0.75,
        'max_live' => 500,
        'consolidation_threshold' => 6,
        'archive_below' => 0.05,
    ];

    protected static ?StartedGenericContainer $postgresContainer = null;

    /**
     * @var array{host: string, port: int, database: string, username: string, password: string}
     */
    protected static array $database;

    #[\Override]
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (env('DB_HOST')) {
            static::$database = [
                'host' => env('DB_HOST'),
                'port' => (int) env('DB_PORT', 5432),
                'database' => env('DB_DATABASE', 'memory_test'),
                'username' => env('DB_USERNAME', 'postgres'),
                'password' => (string) env('DB_PASSWORD', ''),
            ];

            return;
        }

        $container = new GenericContainer('pgvector/pgvector:pg18-trixie');
        $container
            ->withExposedPorts(5432)
            ->withEnvironment([
                'POSTGRES_USER' => 'test_user',
                'POSTGRES_PASSWORD' => 'test_password',
                'POSTGRES_DB' => 'memory_test',
            ])
            ->withWait(new WaitForExec(['pg_isready', '-h', '127.0.0.1', '-U', 'test_user']));

        static::$postgresContainer = $container->start();

        static::$database = [
            'host' => static::$postgresContainer->getHost(),
            'port' => static::$postgresContainer->getMappedPort(5432),
            'database' => 'memory_test',
            'username' => 'test_user',
            'password' => 'test_password',
        ];
    }

    #[\Override]
    public static function tearDownAfterClass(): void
    {
        static::$postgresContainer?->stop();

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        AssistantAgent::$instructions = [];
        AssistantAgent::$history = [];
    }

    protected function getPackageProviders($app): array
    {
        return [
            AiServiceProvider::class,
            MemoryServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'pgsql',
            ...static::$database,
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ]);

        foreach ($this->memoryConfig() as $key => $value) {
            $app['config']->set("memory.{$key}", $value);
        }
    }

    /**
     * Get the memory config the tests run on.
     *
     * @return array<string, mixed>
     */
    protected function memoryConfig(): array
    {
        return self::MEMORY_DEFAULTS;
    }
}
