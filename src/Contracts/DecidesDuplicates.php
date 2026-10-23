<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Contracts;

use Eznix86\AI\Memory\Models\AgentMemory;
use Illuminate\Database\Eloquent\Collection;

interface DecidesDuplicates
{
    /**
     * Decide whether the new memory is added, replaces a candidate, or is already known.
     *
     * @param  Collection<int, AgentMemory>  $candidates
     * @return array{decision: 'add'|'update'|'noop', replace_id: int|null}
     */
    public function decide(AgentMemory $memory, Collection $candidates): array;
}
