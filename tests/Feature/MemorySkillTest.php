<?php

declare(strict_types=1);

use Eznix86\AI\Memory\MemoryContext;
use Laravel\Ai\Responses\Data\ToolCall;
use Tests\Fixtures\AssistantAgent;

test('the agent can load the memory skill instructions', function (): void {
    AssistantAgent::fake([new ToolCall('call-1', 'LoadSkill', ['name' => 'memory']), 'Done.']);

    $response = (new AssistantAgent(new MemoryContext(workspace())))->prompt('Remember that we deploy on Thursdays now.');

    expect((string) $response->steps[0]->toolResults[0]->result)
        ->toContain('# Memory')
        ->toContain('1. Forget the old memory by its id.');
});
