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
     * The id of the conversation the memories are learned in.
     */
    public ?string $conversation;

    /**
     * Create a new memory context.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        public ?Model $user = null,
        ?Model $owner = null,
        string|Model|null $conversation = null,
    ) {
        $this->owner = $owner ?? $user ?? throw new InvalidArgumentException('A memory context needs a user, an owner, or both.');
        $this->conversation = $conversation instanceof Model ? (string) $conversation->getKey() : $conversation;
    }

    /**
     * Get the source recorded on memories the agent writes in this context.
     */
    public function source(): string
    {
        return $this->conversation === null ? 'agent' : "conversation:{$this->conversation}";
    }
}
