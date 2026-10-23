<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Agents;

use Eznix86\AI\Memory\Agents\Concerns\AsksMemoryModel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

class MemoryDecider implements Agent, HasStructuredOutput
{
    use AsksMemoryModel;

    public function instructions(): Stringable|string
    {
        return trim(view('prompts.memory.agents.decider')->render());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'decision' => $schema->string()
                ->enum(['add', 'update', 'noop'])
                ->required(),
            'replace_id' => $schema->integer()
                ->nullable()
                ->description('The id of the memory the new fact replaces, when the decision is update.')
                ->required(),
        ];
    }
}
