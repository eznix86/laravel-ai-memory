<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory;

use Illuminate\Database\Eloquent\Model;

final readonly class MemoryContext
{
    /**
     * Create a new memory context.
     *
     * @param  list<string>  $subjects  Subjects whose memories are always loaded, e.g. "customer:42".
     */
    public function __construct(
        public Model $tenant,
        public ?Model $user = null,
        public ?string $conversationId = null,
        public array $subjects = [],
    ) {}

    /**
     * Get the source recorded on memories the agent writes in this context.
     */
    public function source(): string
    {
        return $this->conversationId === null ? 'agent' : "conversation:{$this->conversationId}";
    }
}
