<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Closure;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Middleware\WithMemory;
use Eznix86\AI\Memory\Skills\MemorySkill;
use Eznix86\AI\Memory\Tools\Forget;
use Eznix86\AI\Memory\Tools\RecallMemory;
use Eznix86\AI\Memory\Tools\Remember;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasSkills;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\PendingStep;
use Laravel\Ai\Promptable;
use Stringable;

class AssistantAgent implements Agent, HasMiddleware, HasSkills, HasTools
{
    use Promptable;

    /**
     * @var list<string|null>
     */
    public static array $instructions = [];

    public function __construct(
        protected MemoryContext $context,
    ) {}

    public function instructions(): Stringable|string
    {
        return 'You are a helpful assistant.';
    }

    public function tools(): iterable
    {
        return [
            new Remember($this->context),
            new Forget($this->context),
            new RecallMemory($this->context),
        ];
    }

    public function skills(): iterable
    {
        return [
            MemorySkill::make(),
        ];
    }

    public function middleware(): array
    {
        return [
            new WithMemory($this->context),
            function (PendingStep $step, Closure $next) {
                static::$instructions[] = $step->instructions;

                return $next($step);
            },
        ];
    }
}
