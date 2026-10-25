<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Jobs\ExtractMemories;
use Eznix86\AI\Memory\Models\AgentMemory;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;

test('the extractor keeps what the user stated', function (array $case): void {
    $workspace = workspace();

    ExtractMemories::dispatchSync($workspace, user(), null, $case['message'], $case['reply']);

    expect(Str::lower((string) AgentMemory::forTenant($workspace)->pluck('content')->implode(' / ')))->toContain(...$case['keywords']);
})->with(IntegrationTestCase::cases('memory', 'extraction_stored'));

test('the extractor ignores what the user did not state', function (array $case): void {
    $workspace = workspace();

    ExtractMemories::dispatchSync($workspace, user(), null, $case['message'], $case['reply']);

    expect(AgentMemory::forTenant($workspace)->pluck('content')->all())->toBe([]);
})->with(IntegrationTestCase::cases('memory', 'extraction_ignored'));
