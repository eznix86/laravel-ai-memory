<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory;

use Illuminate\Database\Eloquent\Model;

final readonly class MemoryContext
{
    /**
     * Create a new memory context.
     */
    public function __construct(
        public Model $owner,
        public ?Model $user = null,
        public ?string $conversation = null,
    ) {}

    /**
     * Get the source recorded on memories the agent writes in this context.
     */
    public function source(): string
    {
        return $this->conversation === null ? 'agent' : "conversation:{$this->conversation}";
    }
}
