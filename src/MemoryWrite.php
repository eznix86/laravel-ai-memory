<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory;

use Eznix86\AI\Memory\Enums\WriteOutcome;
use Eznix86\AI\Memory\Models\AgentMemory;

final readonly class MemoryWrite
{
    /**
     * Create a new write result.
     */
    public function __construct(
        public WriteOutcome $outcome,
        public AgentMemory $memory,
        public ?AgentMemory $replaced = null,
    ) {}
}
