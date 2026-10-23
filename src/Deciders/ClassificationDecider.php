<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Deciders;

use Eznix86\AI\Memory\Contracts\DecidesDuplicates;
use Eznix86\AI\Memory\Models\AgentMemory;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Ai\Classification;
use Laravel\Ai\Classification\Choice;
use Laravel\Ai\Responses\Data\ChoiceAnswer;

class ClassificationDecider implements DecidesDuplicates
{
    /**
     * Ask a classification model to pick one option, one per candidate it could replace.
     *
     * @param  Collection<int, AgentMemory>  $candidates
     * @return array{decision: 'add'|'update'|'noop', replace_id: int|null}
     */
    public function decide(AgentMemory $memory, Collection $candidates): array
    {
        $options = [
            'add' => 'The new fact is about something else than every stored memory.',
            'noop' => 'A stored memory already says the same thing as the new fact.',
            ...$candidates->mapWithKeys(fn (AgentMemory $candidate): array => [
                "replace_{$candidate->id}" => "The new fact changes or contradicts memory #{$candidate->id}.",
            ])->all(),
        ];

        $answer = Classification::of(view('prompts.memory.agents.decider-prompt', ['memory' => $memory, 'candidates' => $candidates])->render())
            ->question('decision', new Choice(trim(view('prompts.memory.classification.decider')->render()), $options))
            ->classify(config('memory.classification_provider'), config('memory.classification_model'))
            ->answer('decision');

        $choice = $answer instanceof ChoiceAnswer ? $answer->choice : 'add';

        return match (true) {
            $choice === 'noop' => ['decision' => 'noop', 'replace_id' => null],
            str_starts_with($choice, 'replace_') => ['decision' => 'update', 'replace_id' => (int) substr($choice, 8)],
            default => ['decision' => 'add', 'replace_id' => null],
        };
    }
}
