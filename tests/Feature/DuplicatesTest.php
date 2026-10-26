<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Agents\MemoryDecider;
use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\Enums\WriteOutcome;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Services\MemoryWriter;
use Eznix86\AI\Memory\Tools\Remember;
use Illuminate\Support\Arr;
use Laravel\Ai\Classification;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ChoiceAnswer;
use Laravel\Ai\Tools\Request;
use Tests\Fixtures\AssistantAgent;

function remember(MemoryContext $context, string $content, string $kind = 'process'): string
{
    return (string) (new Remember($context))->handle(new Request(['kind' => $kind, 'content' => $content]));
}

test('a fact unlike every stored memory is added without asking the decider', function (): void {
    $context = new MemoryContext(owner: workspace());
    memory($context, 'Invoices are sent on Monday.', MemoryKind::Process);

    expect(remember($context, 'Refunds need a manager approval.'))->toStartWith('Remembered as #');

    MemoryDecider::assertNeverPrompted();
});

test('a near duplicate is already known and creates no row', function (): void {
    $context = new MemoryContext(owner: workspace());
    $known = memory($context, 'The team deploys only on Tuesdays.', MemoryKind::Process);

    MemoryDecider::fake([['decision' => 'noop', 'replace_id' => null]]);

    expect(remember($context, 'The team deploys on Tuesdays only.'))->toBe("Already known as #{$known->id}. Nothing was stored.")
        ->and(AgentMemory::count())->toBe(1);

    MemoryDecider::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains("#{$known->id} [process] The team deploys only on Tuesdays."));
});

test('a contradicting fact supersedes the old memory, which no longer loads', function (): void {
    $context = new MemoryContext(owner: workspace());
    $old = memory($context, 'The team deploys only on Tuesdays.', MemoryKind::Process);

    MemoryDecider::fake([['decision' => 'update', 'replace_id' => $old->id]]);

    $result = remember($context, 'The team deploys only on Thursdays.');
    $new = AgentMemory::latest('id')->first();

    expect($result)->toBe("Remembered as #{$new->id}. It replaces #{$old->id}.")
        ->and($old->fresh()->superseded_by)->toBe($new->id);

    AssistantAgent::fake(['Hello']);
    (new AssistantAgent($context))->prompt('Hi');

    expect(AssistantAgent::$instructions[0])->toContain('Thursdays')->not->toContain('Tuesdays');
});

test('a replace id that is not among the candidates is ignored and the fact is added', function (): void {
    $context = new MemoryContext(owner: workspace());
    $candidate = memory($context, 'The team deploys only on Tuesdays.', MemoryKind::Process);
    $unrelated = memory($context, 'Invoices are sent on Monday.', MemoryKind::Process);

    MemoryDecider::fake([['decision' => 'update', 'replace_id' => $unrelated->id]]);

    $write = app(MemoryWriter::class)->write($context, ['kind' => 'process', 'content' => 'The team deploys only on Thursdays.', 'source' => 'test']);

    expect($write->outcome)->toBe(WriteOutcome::Added)
        ->and($candidate->fresh()->superseded_by)->toBeNull()
        ->and($unrelated->fresh()->superseded_by)->toBeNull();
});

test('candidates share the subject and the owner of the new fact', function (): void {
    $user = user();
    $context = new MemoryContext(user: $user, owner: workspace());

    memory($context, 'Customer 42 pays late.', MemoryKind::CustomerNote, ['subject' => 'customer:7']);
    memory($context, 'The user prefers Tuesdays for calls.', MemoryKind::Process);

    app(MemoryWriter::class)->write($context, ['kind' => 'customer_note', 'subject' => 'customer:42', 'content' => 'Customer 42 pays late.', 'source' => 'test']);
    app(MemoryWriter::class)->write($context, ['kind' => 'preference', 'content' => 'The user prefers Tuesdays for calls.', 'source' => 'test']);

    MemoryDecider::assertNeverPrompted();
    expect(AgentMemory::count())->toBe(4);
});

test('the classification decider replaces the memory it picks', function (): void {
    config(['memory.decider' => 'classification']);
    $context = new MemoryContext(owner: workspace());
    $old = memory($context, 'The team deploys only on Tuesdays.', MemoryKind::Process);

    Classification::fake([['decision' => new ChoiceAnswer("replace_{$old->id}", [])]]);

    expect(remember($context, 'The team deploys only on Thursdays.'))->toContain("It replaces #{$old->id}.");

    Classification::assertClassified(fn ($prompt): bool => Arr::exists($prompt->questions['decision']->options, "replace_{$old->id}"));
    MemoryDecider::assertNeverPrompted();
});

test('the classification decider can find a fact already known', function (): void {
    config(['memory.decider' => 'classification']);
    $context = new MemoryContext(owner: workspace());
    $known = memory($context, 'The team deploys only on Tuesdays.', MemoryKind::Process);

    Classification::fake([['decision' => new ChoiceAnswer('noop', [])]]);

    expect(remember($context, 'The team deploys on Tuesdays only.'))->toBe("Already known as #{$known->id}. Nothing was stored.");
});

test('a full context archives its weakest memories before adding, but keeps the confirmed ones', function (): void {
    config(['memory.max_active_per_context' => 3]);
    $context = new MemoryContext(owner: workspace());
    $confirmer = user();

    $confirmed = memory($context, 'Confirmed but weak.', attributes: ['importance' => 0.01, 'creator_type' => $confirmer->getMorphClass(), 'creator_id' => $confirmer->id]);
    $weak = memory($context, 'Weak lesson.', attributes: ['importance' => 0.02]);
    $strong = memory($context, 'Strong lesson.', attributes: ['importance' => 0.9]);

    remember($context, 'Refunds need a manager approval.');

    expect($weak->fresh()->archived_at)->not->toBeNull()
        ->and($confirmed->fresh()->archived_at)->toBeNull()
        ->and($strong->fresh()->archived_at)->toBeNull()
        ->and(AgentMemory::forContext($context)->active()->count())->toBe(3);
});

test('free-form subjects from the model are dropped, so the same fact still meets its duplicate', function (): void {
    $context = new MemoryContext(owner: workspace());
    $tool = new Remember($context);

    $tool->handle(new Request(['kind' => 'process', 'content' => 'Our team deploys only on Tuesdays.', 'subject' => 'team deployment']));

    $old = AgentMemory::sole();
    MemoryDecider::fake([['decision' => 'update', 'replace_id' => $old->id]]);

    $tool->handle(new Request(['kind' => 'process', 'content' => 'Our team deploys only on Thursdays.', 'subject' => 'deployment schedule']));

    expect($old->subject)->toBeNull()
        ->and($old->fresh()->superseded_by)->not->toBeNull();
});

test('a subject written as type:id is kept', function (): void {
    $context = new MemoryContext(owner: workspace());

    (new Remember($context))->handle(new Request(['kind' => 'customer_note', 'content' => 'Customer 42 pays late.', 'subject' => 'customer:42']));

    expect(AgentMemory::sole()->subject)->toBe('customer:42');
});

test('the deciders use the configured timeout', function (): void {
    config(['memory.timeout' => 300]);
    $context = new MemoryContext(owner: workspace());
    memory($context, 'The team deploys only on Tuesdays.', MemoryKind::Process);

    remember($context, 'The team deploys only on Thursdays.');
    MemoryDecider::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->timeout === 300);

    config(['memory.decider' => 'classification']);
    remember($context, 'The team deploys only on Mondays.');
    Classification::assertClassified(fn ($prompt): bool => $prompt->timeout === 300);
});
