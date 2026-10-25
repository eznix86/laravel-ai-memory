<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Middleware;

use Closure;
use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Prompt;
use Eznix86\AI\Memory\Services\MemorySearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Ai\Gateway\StepResult;
use Laravel\Ai\Messages\UserMessage;
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

    public const int RECENT_MESSAGES = 3;

    /**
     * Handle the pending generation step.
     */
    public function handle(PendingStep $step, Closure $next): StepResult
    {
        $key = (string) $step->invocationId;

        if (! Arr::exists($this->loaded, $key)) {
            $this->loaded[$key] = $this->render($this->load($this->recentUserText($step)));
        }

        if ($this->loaded[$key] === null) {
            return $next($step);
        }

        return $next($step->withInstructions(Str::trim($step->instructions."\n\n".$this->loaded[$key])));
    }

    /**
     * Load the user's preferences, the memories most relevant to the conversation, then the strongest ones, and mark them as used.
     *
     * @return Collection<int, AgentMemory>
     */
    protected function load(string $recent): Collection
    {
        $limit = $this->limit ?? config('memory.loaded_limit');

        $preferences = $this->live()->where('kind', MemoryKind::Preference)->when(
            $this->context->user,
            fn (Builder $query, Model $user) => $query->whereBelongsToUser($user),
            fn (Builder $query) => $query->whereRaw('false'),
        )->strongestFirst()->limit($limit)->pluck('id');

        $relevant = Str::trim($recent) === '' || $limit - $preferences->count() < 1 ? collect() : app(MemorySearch::class)
            ->rank($this->context, $recent, min(config('memory.relevant_limit'), $limit - $preferences->count()), except: $preferences->all())
            ->modelKeys();

        $chosen = $preferences->merge($relevant);

        $strongest = $this->live()->whereKeyNot($chosen)->strongestFirst()->limit($limit - $chosen->count())->pluck('id');

        $memories = AgentMemory::query()->whereKey($chosen->merge($strongest))->strongestFirst()->get();

        if ($memories->isNotEmpty()) {
            AgentMemory::query()->whereKey($memories->modelKeys())->toBase()->increment('recall_count', 1, ['last_used_at' => now()]);
        }

        return $memories;
    }

    /**
     * Get the text of the last user messages, so a follow-up still carries the subject named before.
     */
    protected function recentUserText(PendingStep $step): string
    {
        return collect($step->messages)
            ->filter(fn (mixed $message): bool => $message instanceof UserMessage)
            ->take(-self::RECENT_MESSAGES)
            ->map(fn (UserMessage $message): string => (string) $message->content)
            ->implode("\n");
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
