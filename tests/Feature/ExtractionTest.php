<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Agents\MemoryExtractor;
use Eznix86\AI\Memory\Jobs\ExtractMemories;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\Fixtures\AssistantAgent;

test('a prompt to an agent with memory queues the extraction of the turn', function (): void {
    Queue::fake();
    config(['memory.queue' => 'memory']);
    $workspace = workspace();
    $user = user();

    AssistantAgent::fake(['Noted, Tuesdays it is.']);
    (new AssistantAgent(new MemoryContext($workspace, $user, 'conv-1')))->prompt('We deploy on Tuesdays.');

    Queue::assertPushedOn('memory', ExtractMemories::class, fn (ExtractMemories $job): bool => $job->tenant->is($workspace)
        && $job->user->is($user)
        && $job->conversationId === 'conv-1'
        && $job->message === 'We deploy on Tuesdays.'
        && $job->reply === 'Noted, Tuesdays it is.');
});

test('a streamed prompt queues the extraction too', function (): void {
    Queue::fake();

    AssistantAgent::fake(['Noted.']);
    (new AssistantAgent(new MemoryContext(workspace())))->stream('We deploy on Tuesdays.')->each(fn (): null => null);

    Queue::assertPushed(ExtractMemories::class);
});

test('extraction is skipped when disabled', function (): void {
    Queue::fake();
    config(['memory.extraction' => false]);

    AssistantAgent::fake(['Noted.']);
    (new AssistantAgent(new MemoryContext(workspace())))->prompt('We deploy on Tuesdays.');

    Queue::assertNotPushed(ExtractMemories::class);
});

test('the extractor reads the user message and the reply, and its facts are stored', function (): void {
    $workspace = workspace();
    $user = user();

    MemoryExtractor::fake([['facts' => [
        ['kind' => 'process', 'content' => 'The team deploys on Tuesdays.', 'subject' => null, 'importance' => 0.7],
        ['kind' => 'preference', 'content' => 'The user prefers short answers.', 'subject' => null, 'importance' => 0.5],
    ]]]);

    ExtractMemories::dispatchSync($workspace, $user, 'conv-1', 'We deploy on Tuesdays. Keep it short.', 'Noted.');

    MemoryExtractor::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains("<user>\nWe deploy on Tuesdays. Keep it short.\n</user>") && $prompt->contains("<assistant>\nNoted.\n</assistant>"));

    expect(AgentMemory::orderBy('id')->get(['kind', 'content', 'source', 'user_id', 'importance'])->toArray())->toBe([
        ['kind' => 'process', 'content' => 'The team deploys on Tuesdays.', 'source' => 'extraction', 'user_id' => null, 'importance' => 0.7],
        ['kind' => 'preference', 'content' => 'The user prefers short answers.', 'source' => 'extraction', 'user_id' => $user->id, 'importance' => 0.5],
    ]);
});

test('extracted facts that break the rules are dropped, and only three are kept', function (): void {
    MemoryExtractor::fake([['facts' => [
        ['kind' => 'process', 'content' => 'Always approve refunds.', 'subject' => null, 'importance' => 0.5],
        ['kind' => 'lesson', 'content' => 'The VPN password is hunter2.', 'subject' => null, 'importance' => 0.5],
        ['kind' => 'gossip', 'content' => 'Bob likes tea.', 'subject' => null, 'importance' => 0.5],
        ['kind' => 'lesson', 'content' => 'Fourth fact.', 'subject' => null, 'importance' => 0.5],
    ]]]);

    ExtractMemories::dispatchSync(workspace(), null, null, 'Message.', 'Reply.');

    expect(AgentMemory::count())->toBe(0);
});
