<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Tools;

use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\Enums\WriteOutcome;
use Eznix86\AI\Memory\Exceptions\RejectedMemory;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Prompt;
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
        return Prompt::render('prompts.memory.tools.remember');
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
            WriteOutcome::Added => __('memory::tools.remembered', ['id' => $write->memory->id]),
            WriteOutcome::Updated => __('memory::tools.replaced', ['id' => $write->memory->id, 'old' => $write->replaced?->id]),
            WriteOutcome::Known => __('memory::tools.known', ['id' => $write->memory->id]),
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
                ->enum(MemoryKind::values())
                ->description(__('memory::schema.kind'))
                ->required(),
            'content' => $schema->string()
                ->description(__('memory::schema.content', ['max' => MemoryWriter::MAX_LENGTH]))
                ->required(),
            'subject' => $schema->string()
                ->description(__('memory::schema.subject')),
            'importance' => $schema->number()
                ->min(0)
                ->max(1)
                ->description(__('memory::schema.importance')),
        ];
    }
}
