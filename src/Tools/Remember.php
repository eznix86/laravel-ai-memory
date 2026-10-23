<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Tools;

use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\Enums\WriteOutcome;
use Eznix86\AI\Memory\Exceptions\RejectedMemory;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Services\MemoryWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class Remember implements Tool
{
    /**
     * Create a new tool instance.
     */
    public function __construct(
        protected MemoryContext $context,
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return trim(view('prompts.memory.tools.remember')->render());
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        try {
            $write = app(MemoryWriter::class)->write($this->context, [
                'kind' => $request['kind'],
                'content' => $request['content'],
                'subject' => MemoryWriter::subject($request['subject'] ?? null),
                'importance' => $request['importance'] ?? null,
                'source' => $this->context->source(),
            ]);
        } catch (RejectedMemory $exception) {
            return $exception->getMessage();
        }

        return match ($write->outcome) {
            WriteOutcome::Added => "Remembered as #{$write->memory->id}.",
            WriteOutcome::Updated => "Remembered as #{$write->memory->id}. It replaces #{$write->replaced?->id}.",
            WriteOutcome::Known => "Already known as #{$write->memory->id}. Nothing was stored.",
        };
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()
                ->enum(array_column(MemoryKind::cases(), 'value'))
                ->description('The kind of fact.')
                ->required(),
            'content' => $schema->string()
                ->description('One fact, at most '.MemoryWriter::MAX_LENGTH.' characters.')
                ->required(),
            'subject' => $schema->string()
                ->description('The id of the one thing the fact is about, as type:id, such as customer:42 or project:apollo. Leave it out when the fact is general.'),
            'importance' => $schema->number()
                ->min(0)
                ->max(1)
                ->description('How much the fact matters, from 0 to 1. The default is 0.5.'),
        ];
    }
}
