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

        $this->app->bind(DecidesDuplicates::class, fn (): DecidesDuplicates => match (config('memory.decider')) {
            'classification' => new ClassificationDecider,
            default => new AgentDecider,
        });

        $this->app->bind(GuardsPermissions::class, fn (): GuardsPermissions => match (config('memory.permission_guard')) {
            'agent' => new AgentPermissionGuard,
            'classification' => new ClassificationPermissionGuard,
            default => new NoPermissionGuard,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
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
        }
    }
}
