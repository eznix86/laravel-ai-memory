<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Agents;

use Eznix86\AI\Memory\Agents\Concerns\AsksMemoryModel;
use Eznix86\AI\Memory\Prompt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

class PermissionChecker implements Agent, HasStructuredOutput
{
    use AsksMemoryModel;

    public function instructions(): Stringable|string
    {
        return Prompt::render('prompts.memory.agents.permission');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'grants_permission' => $schema->boolean()->required(),
        ];
    }
}
