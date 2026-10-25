<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\Jobs\ConsolidateMemories;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;

test('the consolidator keeps what is still true and drops what changed', function (array $case): void {
    $workspace = workspace();
    collect($case['notes'])->each(fn (string $note, int $age): AgentMemory => memory(new MemoryContext($workspace), $note, MemoryKind::CustomerNote, [
        'subject' => $case['subject'],
        'created_at' => now()->subDays(60 - $age * 10),
    ]));

    ConsolidateMemories::dispatchSync($workspace);

    $merged = Str::lower((string) AgentMemory::forTenant($workspace)->live()->sole()->content);
    expect($merged)->toContain(...$case['must_contain'])
        ->and(Str::contains($merged, $case['must_not_contain']))->toBeFalse();
})->with(IntegrationTestCase::cases('memory', 'consolidation'));
