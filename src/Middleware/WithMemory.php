<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Middleware;

use Closure;
use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Prompt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Ai\Gateway\StepResult;
use Laravel\Ai\PendingStep;

class WithMemory
{
    /**
     * The rendered memories, keyed by invocation id.
     *
     * @var array<string, string|null>
     */
    protected array $loaded = [];

    /**
     * Create a new memory middleware instance.
     */
    public function __construct(
        protected MemoryContext $context,
        protected ?int $limit = null,
    ) {}

    /**
     * Handle the pending generation step.
     */
    public function handle(PendingStep $step, Closure $next): StepResult
    {
        $key = (string) $step->invocationId;

        if (! Arr::exists($this->loaded, $key)) {
            $this->loaded[$key] = $this->render($this->load());
        }

        if ($this->loaded[$key] === null) {
            return $next($step);
        }

        return $next($step->withInstructions(Str::trim($step->instructions."\n\n".$this->loaded[$key])));
    }

    /**
     * Load the strongest live memories, always including the pinned ones, and mark them as used.
     *
     * @return Collection<int, AgentMemory>
     */
    protected function load(): Collection
    {
        $limit = $this->limit ?? config('memory.loaded_limit');

        $pinned = $this->live()->where(fn (Builder $query) => $this->pinned($query))->strongestFirst()->limit($limit)->pluck('id');

        $others = $this->live()->whereKeyNot($pinned)->strongestFirst()->limit($limit - $pinned->count())->pluck('id');

        $memories = AgentMemory::query()->whereKey($pinned->merge($others))->strongestFirst()->get();

        if ($memories->isNotEmpty()) {
            AgentMemory::query()->whereKey($memories->modelKeys())->toBase()->increment('recall_count', 1, ['last_used_at' => now()]);
        }

        return $memories;
    }

    /**
     * Start a query for the live memories in the context.
     *
     * @return Builder<AgentMemory>
     */
    protected function live(): Builder
    {
        return AgentMemory::query()->forContext($this->context)->live();
    }

    /**
     * Limit the query to the user's preferences and the memories about the context subjects.
     *
     * @param  Builder<AgentMemory>  $query
     */
    protected function pinned(Builder $query): void
    {
        $query->whereIn('subject', $this->context->subjects);

        if ($this->context->user instanceof Model) {
            $query->orWhere(fn (Builder $query) => $query->where('kind', MemoryKind::Preference)->whereBelongsToUser($this->context->user));
        }
    }

    /**
     * Render the memories for the agent instructions.
     *
     * @param  Collection<int, AgentMemory>  $memories
     */
    protected function render(Collection $memories): ?string
    {
        if ($memories->isEmpty()) {
            return null;
        }

        return Prompt::render('prompts.memory.instructions', ['memories' => $memories]);
    }
}
