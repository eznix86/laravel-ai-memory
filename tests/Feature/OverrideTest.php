<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Agents\MemoryDecider;
use Eznix86\AI\Memory\Agents\MemoryExtractor;
use Eznix86\AI\Memory\Contracts\DecidesDuplicates;
use Eznix86\AI\Memory\Deciders\AgentDecider;
use Eznix86\AI\Memory\Embedder;
use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\Jobs\ExtractMemories;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Tools\Remember;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Ai\Tools\Request;
use Tests\Fixtures\TeamExtractor;

test('an app can replace a memory agent with its own class', function (): void {
    app()->bind(MemoryExtractor::class, TeamExtractor::class);
    TeamExtractor::fake([['facts' => [
        ['kind' => 'process', 'content' => 'The team deploys on Tuesdays.', 'subject' => null, 'importance' => 0.7],
    ]]]);

    ExtractMemories::dispatchSync(workspace(), null, null, 'We deploy on Tuesdays.', 'Noted.');

    TeamExtractor::assertPrompted(fn (): bool => true);
    expect(AgentMemory::sole()->content)->toBe('The team deploys on Tuesdays.');
});

test('an app can replace the decider class that the config picks', function (): void {
    app()->bind(AgentDecider::class, fn (): DecidesDuplicates => new class implements DecidesDuplicates
    {
        public function decide(AgentMemory $memory, Collection $candidates): array
        {
            return ['decision' => 'noop', 'replace_id' => null];
        }
    });
    $context = new MemoryContext(owner: workspace());
    $known = memory($context, 'The team deploys only on Tuesdays.', MemoryKind::Process);

    $answer = (string) (new Remember($context))->handle(new Request(['kind' => 'process', 'content' => 'The team deploys on Tuesdays only.']));

    expect($answer)->toBe("Already known as #{$known->id}. Nothing was stored.");
    MemoryDecider::assertNeverPrompted();
});

test('an app can replace the embedder', function (): void {
    $dimensions = app(Embedder::class)->dimensions();
    app()->bind(Embedder::class, fn (): Embedder => new class($dimensions) extends Embedder
    {
        public function __construct(protected int $size) {}

        public function embed(string $text): array
        {
            return array_fill(0, $this->size, 1 / sqrt($this->size));
        }
    });
    $context = new MemoryContext(owner: workspace());

    (new Remember($context))->handle(new Request(['kind' => 'process', 'content' => 'Invoices are sent on Monday.']));

    expect(AgentMemory::sole()->embedding)->toHaveCount($dimensions)->each->toEqualWithDelta(1 / sqrt($dimensions), 1e-6);
});
