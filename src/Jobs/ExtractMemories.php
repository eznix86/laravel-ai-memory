<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Jobs;

use Eznix86\AI\Memory\Agents\MemoryExtractor;
use Eznix86\AI\Memory\Exceptions\RejectedMemory;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Prompt;
use Eznix86\AI\Memory\Services\Grounding;
use Eznix86\AI\Memory\Services\MemoryWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExtractMemories implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Drop the job when its owner or user was deleted, since there is nothing left to do.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @param  array<int, array{role: string, text: string}>  $earlier
     */
    public function __construct(
        public Model $owner,
        public ?Model $user,
        public ?string $conversation,
        public string $message,
        public string $reply,
        public array $earlier = [],
    ) {
        $this->onQueue(config('memory.queue'));
    }

    /**
     * Store the facts the user stated or confirmed in the turn, read with the messages before it.
     */
    public function handle(MemoryWriter $writer, Grounding $grounding): void
    {
        $context = new MemoryContext(user: $this->user, owner: $this->owner, conversation: $this->conversation);

        $answer = app(MemoryExtractor::class)->ask(Prompt::render('prompts.memory.agents.extractor-prompt', [
            'earlier' => $this->earlier,
            'message' => $this->message,
            'reply' => $this->reply,
        ]));

        $said = collect($this->earlier)->where('role', 'user')->pluck('text')->push($this->message)->implode("\n");

        collect((array) ($answer['facts'] ?? []))
            ->take(MemoryExtractor::MAX_FACTS)
            ->filter(fn (array $fact): bool => $grounding->isGrounded((string) ($fact['content'] ?? ''), $said))
            ->each(function (array $fact) use ($writer, $context): void {
                try {
                    $writer->write($context, [
                        'kind' => $fact['kind'] ?? null,
                        'content' => $fact['content'] ?? null,
                        'subject' => MemoryWriter::subject($fact['subject'] ?? null),
                        'importance' => $fact['importance'] ?? null,
                        'source' => 'extraction',
                        'personal' => null,
                    ]);
                } catch (RejectedMemory) {
                    return;
                }
            });
    }
}
