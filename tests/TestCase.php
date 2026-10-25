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

    protected static ?StartedGenericContainer $postgresContainer = null;

    /**
     * @var array{host: string, port: int, database: string, username: string, password: string}
     */
    protected static array $database;

    #[\Override]
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (getenv('MEMORY_TEST_DB_HOST')) {
            static::$database = [
                'host' => getenv('MEMORY_TEST_DB_HOST'),
                'port' => (int) (getenv('MEMORY_TEST_DB_PORT') ?: 5432),
                'database' => getenv('MEMORY_TEST_DB_DATABASE') ?: 'memory_test',
                'username' => getenv('MEMORY_TEST_DB_USERNAME') ?: 'postgres',
                'password' => (string) getenv('MEMORY_TEST_DB_PASSWORD'),
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
    }
}
