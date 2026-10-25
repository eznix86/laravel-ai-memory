<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Agents;

use Eznix86\AI\Memory\Agents\Concerns\AsksMemoryModel;
use Eznix86\AI\Memory\Prompt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

class MemoryDecider implements Agent, HasProviderOptions, HasStructuredOutput
{
    use AsksMemoryModel;

    public function instructions(): Stringable|string
    {
        return Prompt::render('prompts.memory.agents.decider');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'decision' => $schema->string()
                ->enum(['add', 'update', 'noop'])
                ->required(),
            'replace_id' => $schema->integer()
                ->nullable()
                ->description(__('memory::schema.replace_id'))
                ->required(),
        ];
    }
}
