<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Tools\RecallMemory;
use Tests\Integration\IntegrationTestCase;

test('recall ranks first the memory that answers the question', function (array $case): void {
    $context = new MemoryContext(user: user(), owner: workspace());
    collect(IntegrationTestCase::cases('memory', 'recall_memories'))->each(fn (array $memory): AgentMemory => seed(
        $context, $memory[0]['content'], MemoryKind::from($memory[0]['kind']), ['subject' => $memory[0]['subject'] ?? null],
    ));

    $top = (new RecallMemory($context))->search($case['query'])->first();

    expect($top?->content)->toBe($case['expect']);
})->with(IntegrationTestCase::cases('memory', 'recall'));
