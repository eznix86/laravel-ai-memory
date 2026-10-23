<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Closure;
use Eznix86\AI\Memory\Middleware\WithMemory;
use Eznix86\AI\Memory\Tools\RecallMemory;
use Eznix86\AI\Memory\Tools\StoreMemory;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\PendingStep;
use Laravel\Ai\Promptable;
use Stringable;

class MemoryAgent implements Agent, HasMiddleware, HasTools
{
    use Promptable;

    public static ?string $instructions = null;

    public function __construct(
        protected array $context = [],
        protected int $recallLimit = 10,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'You are a helpful assistant with memory capabilities. You can store and recall information from previous conversations.';
    }

    /**
     * Get the tools available to the agent.
     */
    public function tools(): iterable
    {
        return [
            (new RecallMemory)->context($this->context)->limit($this->recallLimit),
            (new StoreMemory)->context($this->context),
        ];
    }

    /**
     * Get the middleware for the agent.
     */
    public function middleware(): array
    {
        return [
            new WithMemory($this->context),
            function (PendingStep $step, Closure $next) {
                static::$instructions = $step->instructions;

                return $next($step);
            },
        ];
    }
}
