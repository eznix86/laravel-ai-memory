<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Agents;

use Eznix86\AI\Memory\Agents\Concerns\AsksMemoryModel;
use Eznix86\AI\Memory\Services\MemoryWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

class MemoryConsolidator implements Agent, HasStructuredOutput
{
    use AsksMemoryModel;

    public function instructions(): Stringable|string
    {
        return trim(view('prompts.memory.agents.consolidator')->render());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'content' => $schema->string()
                ->description('One note, at most '.MemoryWriter::MAX_LENGTH.' characters.')
                ->required(),
            'importance' => $schema->number()
                ->min(0)
                ->max(1)
                ->required(),
        ];
    }
}
