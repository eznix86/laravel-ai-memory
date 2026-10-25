<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final readonly class MemoryContext
{
    /**
     * The model the memories belong to: the given owner, or the user when there is none.
     */
    public Model $owner;

    /**
     * Create a new memory context.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        public ?Model $user = null,
        ?Model $owner = null,
        public ?string $conversation = null,
    ) {
        $this->owner = $owner ?? $user ?? throw new InvalidArgumentException('A memory context needs a user, an owner, or both.');
    }

    /**
     * Get the source recorded on memories the agent writes in this context.
     */
    public function source(): string
    {
        return $this->conversation === null ? 'agent' : "conversation:{$this->conversation}";
    }
}
