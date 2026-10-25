<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Agents;

use Eznix86\AI\Memory\Agents\Concerns\AsksMemoryModel;
use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\Prompt;
use Eznix86\AI\Memory\Services\MemoryWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

class MemoryExtractor implements Agent, HasStructuredOutput
{
    use AsksMemoryModel;

    public const int MAX_FACTS = 3;

    public function instructions(): Stringable|string
    {
        return Prompt::render('prompts.memory.agents.extractor');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'facts' => $schema->array()
                ->max(self::MAX_FACTS)
                ->items($schema->object([
                    'kind' => $schema->string()->enum(MemoryKind::values())->required(),
                    'content' => $schema->string()->description(__('memory::schema.content', ['max' => MemoryWriter::MAX_LENGTH]))->required(),
                    'subject' => $schema->string()->nullable()->required(),
                    'importance' => $schema->number()->min(0)->max(1)->required(),
                ])->withoutAdditionalProperties())
                ->required(),
        ];
    }
}
