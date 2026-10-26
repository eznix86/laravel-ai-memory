<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory;

use Eznix86\AI\Memory\Console\ConsolidateCommand;
use Eznix86\AI\Memory\Contracts\DecidesDuplicates;
use Eznix86\AI\Memory\Contracts\GuardsPermissions;
use Eznix86\AI\Memory\Deciders\AgentDecider;
use Eznix86\AI\Memory\Deciders\ClassificationDecider;
use Eznix86\AI\Memory\Listeners\QueueMemoryExtraction;
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\PermissionGuards\AgentPermissionGuard;
use Eznix86\AI\Memory\PermissionGuards\ClassificationPermissionGuard;
use Eznix86\AI\Memory\PermissionGuards\NoPermissionGuard;
use Eznix86\AI\Memory\Policies\MemoryPolicy;
use Eznix86\AI\Memory\Services\MemoryManager;
use Eznix86\AI\Memory\Services\MemoryWriter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;

class MemoryServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/memory.php', 'memory');

        $this->app->singleton(MemoryWriter::class);
        $this->app->singleton(MemoryManager::class);

        $this->app->bind(DecidesDuplicates::class, fn (Application $app): DecidesDuplicates => $app->make(match (config('memory.decider')) {
            'classification' => ClassificationDecider::class,
            default => AgentDecider::class,
        }));

        $this->app->bind(GuardsPermissions::class, fn (Application $app): GuardsPermissions => $app->make(match (config('memory.permission_guard')) {
            'agent' => AgentPermissionGuard::class,
            'classification' => ClassificationPermissionGuard::class,
            default => NoPermissionGuard::class,
        }));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'memory');
        $this->callAfterResolving('view', fn (Factory $view) => $view->addLocation(__DIR__.'/../resources/views'));

        Event::listen([AgentPrompted::class, AgentStreamed::class], QueueMemoryExtraction::class);

        if (Gate::getPolicyFor(AgentMemory::class) === null) {
            Gate::policy(AgentMemory::class, MemoryPolicy::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([ConsolidateCommand::class]);

            $this->publishes([
                __DIR__.'/../config/memory.php' => config_path('memory.php'),
            ], 'memory-config');

            $this->publishes([
                __DIR__.'/../resources/views/prompts/memory' => resource_path('views/prompts/memory'),
            ], 'memory-views');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/memory'),
            ], 'memory-lang');
        }
    }
}
