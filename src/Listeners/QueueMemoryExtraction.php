<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Listeners;

use Eznix86\AI\Memory\Contracts\HasMemory;
use Eznix86\AI\Memory\Jobs\ExtractMemories;
use Laravel\Ai\Events\AgentPrompted;

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
        );
    }
}
