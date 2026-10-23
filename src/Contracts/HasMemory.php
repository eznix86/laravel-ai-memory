<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Contracts;

use Eznix86\AI\Memory\MemoryContext;

interface HasMemory
{
    /**
     * Get the memory context the agent reads and writes.
     */
    public function memoryContext(): MemoryContext;
}
