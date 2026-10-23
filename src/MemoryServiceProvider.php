<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory;

use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Policies\MemoryPolicy;
use Eznix86\AI\Memory\Services\MemoryManager;
use Eznix86\AI\Memory\Services\MemoryWriter;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->callAfterResolving('view', fn (Factory $view) => $view->addLocation(__DIR__.'/../resources/views'));

        if (Gate::getPolicyFor(AgentMemory::class) === null) {
            Gate::policy(AgentMemory::class, MemoryPolicy::class);
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/memory.php' => config_path('memory.php'),
            ], 'memory-config');

            $this->publishes([
                __DIR__.'/../resources/views/prompts/memory' => resource_path('views/prompts/memory'),
            ], 'memory-views');
        }
    }
}
