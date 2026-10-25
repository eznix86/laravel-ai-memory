<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Policies;

use Eznix86\AI\Memory\Models\AgentMemory;
use Illuminate\Database\Eloquent\Model;

class MemoryPolicy
{
    /**
     * Determine whether the user can edit the memory.
     */
    public function update(Model $user, AgentMemory $memory): bool
    {
        return $this->manages($user, $memory);
    }

    /**
     * Determine whether the user can delete the memory.
     */
    public function delete(Model $user, AgentMemory $memory): bool
    {
        return $this->manages($user, $memory);
    }

    /**
     * Determine whether the user owns the memory, or administers its owner when it is shared.
     */
    protected function manages(Model $user, AgentMemory $memory): bool
    {
        if ($memory->user_id === null) {
            return $memory->owner instanceof Model && $this->administers($user, $memory->owner);
        }

        return $memory->user_type === $user->getMorphClass() && $memory->user_id === (string) $user->getKey();
    }

    /**
     * Determine whether the user administers the owner. Extend this policy to grant it.
     */
    protected function administers(Model $user, Model $owner): bool
    {
        return false;
    }
}
