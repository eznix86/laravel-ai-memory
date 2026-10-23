<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Middleware;

use Closure;
use Eznix86\AI\Memory\Services\MemoryManager;
use Laravel\Ai\Gateway\StepResult;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\PendingStep;

class WithMemory
{
    /**
     * The recalled memories, keyed by invocation id.
     *
     * @var array<string, string|null>
     */
    protected array $recalled = [];

    /**
     * Create a new memory middleware instance.
     *
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        protected array $context = [],
        protected ?int $limit = null,
    ) {}

    /**
     * Handle the pending generation step.
     */
    public function handle(PendingStep $step, Closure $next): StepResult
    {
        if ($this->context === []) {
            return $next($step);
        }

        $key = (string) $step->invocationId;

        if (! array_key_exists($key, $this->recalled)) {
            $this->recalled[$key] = $this->recall($step);
        }

        $memories = $this->recalled[$key];

        if ($memories === null) {
            return $next($step);
        }

        return $next($step->withInstructions(trim($step->instructions."\n\n".$memories)));
    }

    /**
     * Recall the memories relevant to the latest user message.
     */
    protected function recall(PendingStep $step): ?string
    {
        $message = collect($step->messages)->last(fn ($message): bool => $message instanceof UserMessage);

        if (blank($message?->content)) {
            return null;
        }

        $memories = app(MemoryManager::class)->recall(
            $message->content,
            $this->context,
            $this->limit ?? config('memory.middleware_recall_limit', 5),
        );

        if ($memories->isEmpty()) {
            return null;
        }

        return "Relevant memories from previous conversations:\n".$memories->map(fn ($memory): string => "- {$memory->content}")->implode("\n");
    }
}
