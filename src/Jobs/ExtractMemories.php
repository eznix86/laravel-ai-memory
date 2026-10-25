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
     * Create a new job instance.
     */
    public function __construct(
        public Model $tenant,
        public ?Model $user,
        public ?string $conversationId,
        public string $message,
        public string $reply,
    ) {
        $this->onQueue(config('memory.queue'));
    }

    /**
     * Store the facts the user stated or confirmed in the turn.
     */
    public function handle(MemoryWriter $writer, Grounding $grounding): void
    {
        $context = new MemoryContext($this->tenant, $this->user, $this->conversationId);

        $answer = (new MemoryExtractor)->ask(Prompt::render('prompts.memory.agents.extractor-prompt', [
            'message' => $this->message,
            'reply' => $this->reply,
        ]));

        collect((array) ($answer['facts'] ?? []))
            ->take(MemoryExtractor::MAX_FACTS)
            ->filter(fn (array $fact): bool => $grounding->isGrounded((string) ($fact['content'] ?? ''), $this->message))
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
