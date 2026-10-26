<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Tools\RecallMemory;
use Tests\Evals\EvalTestCase;

test('recall ranks first the memory that answers the question', function (array $case): void {
    $context = $this->context();
    $this->rememberAll($context, EvalTestCase::data('memory', 'recall_memories'));

    $found = (new RecallMemory($context))->search($case['query']);

    expect($found->first()?->content)->toBe($case['expect']);
})->with(EvalTestCase::cases('memory', 'recall'));
