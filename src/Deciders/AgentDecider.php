<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Deciders;

use Eznix86\AI\Memory\Agents\MemoryDecider;
use Eznix86\AI\Memory\Contracts\DecidesDuplicates;
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Prompt;
use Illuminate\Database\Eloquent\Collection;

class AgentDecider implements DecidesDuplicates
{
    /**
     * Ask a text model with structured output.
     *
     * @param  Collection<int, AgentMemory>  $candidates
     * @return array{decision: 'add'|'update'|'noop', replace_id: int|null}
     */
    public function decide(AgentMemory $memory, Collection $candidates): array
    {
        $answer = (new MemoryDecider)->ask(Prompt::render('prompts.memory.agents.decider-prompt', [
            'memory' => $memory,
            'candidates' => $candidates,
        ]));

        return [
            'decision' => collect(['add', 'update', 'noop'])->containsStrict($answer['decision'] ?? null) ? $answer['decision'] : 'add',
            'replace_id' => isset($answer['replace_id']) ? (int) $answer['replace_id'] : null,
        ];
    }
}
