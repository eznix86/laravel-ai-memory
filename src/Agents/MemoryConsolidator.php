<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Agents;

use Eznix86\AI\Memory\Agents\Concerns\AsksMemoryModel;
use Eznix86\AI\Memory\Prompt;
use Eznix86\AI\Memory\Services\MemoryWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

class MemoryConsolidator implements Agent, HasProviderOptions, HasStructuredOutput
{
    use AsksMemoryModel;

    public function instructions(): Stringable|string
    {
        return Prompt::render('prompts.memory.agents.consolidator');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'content' => $schema->string()
                ->description(__('memory::schema.note', ['max' => MemoryWriter::MAX_LENGTH]))
                ->required(),
            'importance' => $schema->number()
                ->min(0)
                ->max(1)
                ->required(),
        ];
    }
}
