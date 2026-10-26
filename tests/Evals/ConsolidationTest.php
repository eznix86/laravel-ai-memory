<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Jobs\ConsolidateMemories;
use Tests\Evals\EvalTestCase;

test('the consolidator keeps what is still true and drops what changed', function (array $case): void {
    $context = $this->context();
    $this->rememberAll($context, $case['memories']);

    ConsolidateMemories::dispatchSync($context->owner);

    expect($this->memoriesOf($context))->toHaveCount(1)
        ->and($this->rememberedText($context))->toContain(...$case['must_contain'])->not->toContain(...$case['must_not_contain']);
})->with(EvalTestCase::cases('memory', 'consolidation'));
