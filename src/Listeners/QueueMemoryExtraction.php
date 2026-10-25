<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Listeners;

use Eznix86\AI\Memory\Contracts\HasMemory;
use Eznix86\AI\Memory\Jobs\ExtractMemories;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;

class QueueMemoryExtraction
{
    /**
     * Queue the extraction of facts from a turn of an agent that has memory.
     */
    public function handle(AgentPrompted $event): void
    {
        $agent = $event->prompt->agent;

        if (! config('memory.extraction') || ! $agent instanceof HasMemory) {
            return;
        }

        $context = $agent->memory();

        ExtractMemories::dispatch(
            $context->owner,
            $context->user,
            $event->response->conversationId ?? $context->conversation,
            $event->prompt->prompt,
            $event->response->text,
            $this->earlier($event),
        );
    }

    /**
     * The user and assistant messages before the turn, up to the extraction window.
     *
     * @return array<int, array{role: string, text: string}>
     */
    protected function earlier(AgentPrompted $event): array
    {
        $window = (int) config('memory.extraction_window');

        if ($window <= 0) {
            return [];
        }

        $agent = $event->prompt->agent;

        $messages = collect([
            ...($event->prompt->messages ?? []),
            ...($agent instanceof Conversational ? $agent->messages() : []),
        ])
            ->map(fn (mixed $message): Message => Message::tryFrom($message))
            ->filter(fn (Message $message): bool => in_array($message->role, [MessageRole::User, MessageRole::Assistant], true) && filled($message->content))
            ->map(fn (Message $message): array => ['role' => $message->role->value, 'text' => (string) $message->content])
            ->values();

        if ($messages->slice(-2)->values()->all() === [
            ['role' => 'user', 'text' => $event->prompt->prompt],
            ['role' => 'assistant', 'text' => $event->response->text],
        ]) {
            $messages = $messages->slice(0, -2);
        }

        return $messages->slice(-$window)->values()->all();
    }
}
