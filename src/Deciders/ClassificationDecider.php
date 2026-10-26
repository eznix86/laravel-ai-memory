<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Deciders;

use Eznix86\AI\Memory\Contracts\DecidesDuplicates;
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Prompt;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
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
            'add' => __('memory::classification.add'),
            'noop' => __('memory::classification.noop'),
            ...$candidates->mapWithKeys(fn (AgentMemory $candidate): array => [
                "replace_{$candidate->id}" => __('memory::classification.replace', ['id' => $candidate->id]),
            ])->all(),
        ];

        $answer = Classification::of(Prompt::render('prompts.memory.agents.decider-prompt', ['memory' => $memory, 'candidates' => $candidates]))
            ->question('decision', new Choice(Prompt::render('prompts.memory.classification.decider'), $options))
            ->timeout(config('memory.timeout'))
            ->classify(config('memory.classification_provider'), config('memory.classification_model'))
            ->answer('decision');

        $choice = $answer instanceof ChoiceAnswer ? $answer->choice : 'add';

        return match (true) {
            $choice === 'noop' => ['decision' => 'noop', 'replace_id' => null],
            Str::startsWith($choice, 'replace_') => ['decision' => 'update', 'replace_id' => (int) Str::after($choice, 'replace_')],
            default => ['decision' => 'add', 'replace_id' => null],
        };
    }
}
