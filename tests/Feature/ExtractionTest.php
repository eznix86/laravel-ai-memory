<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Agents\MemoryExtractor;
use Eznix86\AI\Memory\Jobs\ExtractMemories;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\Fixtures\AssistantAgent;
use Tests\Fixtures\RememberingAgent;

test('a prompt to an agent with memory queues the extraction of the turn', function (): void {
    Queue::fake();
    config(['memory.queue' => 'memory']);
    $workspace = workspace();
    $user = user();

    AssistantAgent::fake(['Noted, Tuesdays it is.']);
    (new AssistantAgent(new MemoryContext(user: $user, owner: $workspace, conversation: 'conv-1')))->prompt('We deploy on Tuesdays.');

    Queue::assertPushedOn('memory', ExtractMemories::class, fn (ExtractMemories $job): bool => $job->owner->is($workspace)
        && $job->user->is($user)
        && $job->conversation === 'conv-1'
        && $job->message === 'We deploy on Tuesdays.'
        && $job->reply === 'Noted, Tuesdays it is.');
});

test('the extraction gets the messages before the turn, up to the extraction window', function (): void {
    Queue::fake();
    config(['memory.extraction_window' => 3]);
    AssistantAgent::$history = [
        new UserMessage('I am training for a powerlifting meet.'),
        new AssistantMessage('Nice! What is your weight class?'),
        new UserMessage('Under 90.'),
        new AssistantMessage('Which federation?'),
    ];

    AssistantAgent::fake(['Got it.']);
    (new AssistantAgent(new MemoryContext(owner: workspace())))->prompt('IPF, 88 kg.');

    Queue::assertPushed(ExtractMemories::class, fn (ExtractMemories $job): bool => $job->earlier === [
        ['role' => 'assistant', 'text' => 'Nice! What is your weight class?'],
        ['role' => 'user', 'text' => 'Under 90.'],
        ['role' => 'assistant', 'text' => 'Which federation?'],
    ] && $job->message === 'IPF, 88 kg.');
});

test('a window of zero sends the turn alone', function (): void {
    Queue::fake();
    config(['memory.extraction_window' => 0]);
    AssistantAgent::$history = [new UserMessage('I am training for a powerlifting meet.')];

    AssistantAgent::fake(['Got it.']);
    (new AssistantAgent(new MemoryContext(owner: workspace())))->prompt('IPF, 88 kg.');

    Queue::assertPushed(ExtractMemories::class, fn (ExtractMemories $job): bool => $job->earlier === []);
});

test('a remembered conversation sends the earlier turns, not the turn it just stored', function (): void {
    $this->loadMigrationsFrom(__DIR__.'/../../vendor/laravel/ai/database/migrations');
    Queue::fake();
    $workspace = workspace();
    $agent = (new RememberingAgent(new MemoryContext(owner: $workspace)))->forParticipant($workspace);

    RememberingAgent::fake(['Nice! What is your weight class?', 'Got it.']);
    $agent->prompt('I am training for a powerlifting meet.');
    $agent->prompt('88 kg.');

    Queue::assertPushed(ExtractMemories::class, fn (ExtractMemories $job): bool => $job->message === 'I am training for a powerlifting meet.' && $job->earlier === []);
    Queue::assertPushed(ExtractMemories::class, fn (ExtractMemories $job): bool => $job->message === '88 kg.' && $job->earlier === [
        ['role' => 'user', 'text' => 'I am training for a powerlifting meet.'],
        ['role' => 'assistant', 'text' => 'Nice! What is your weight class?'],
    ]);
});

test('a streamed prompt queues the extraction too', function (): void {
    Queue::fake();

    AssistantAgent::fake(['Noted.']);
    (new AssistantAgent(new MemoryContext(owner: workspace())))->stream('We deploy on Tuesdays.')->each(fn (): null => null);

    Queue::assertPushed(ExtractMemories::class);
});

test('extraction is skipped when disabled', function (): void {
    Queue::fake();
    config(['memory.extraction' => false]);

    AssistantAgent::fake(['Noted.']);
    (new AssistantAgent(new MemoryContext(owner: workspace())))->prompt('We deploy on Tuesdays.');

    Queue::assertNotPushed(ExtractMemories::class);
});

test('the extractor reads the user message and the reply, and its facts are stored', function (): void {
    $workspace = workspace();
    $user = user();

    MemoryExtractor::fake([['facts' => [
        ['kind' => 'process', 'content' => 'The team deploys on Tuesdays.', 'subject' => null, 'importance' => 0.7],
        ['kind' => 'preference', 'content' => 'The user prefers short answers.', 'subject' => null, 'importance' => 0.5],
    ]]]);

    ExtractMemories::dispatchSync($workspace, $user, 'conv-1', 'We deploy on Tuesdays. I prefer short answers.', 'Noted.');

    MemoryExtractor::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains("<user>\nWe deploy on Tuesdays. I prefer short answers.\n</user>") && $prompt->contains("<assistant>\nNoted.\n</assistant>"));

    expect(AgentMemory::orderBy('id')->get(['kind', 'content', 'source', 'user_id', 'importance'])->toArray())->toBe([
        ['kind' => 'process', 'content' => 'The team deploys on Tuesdays.', 'source' => 'extraction', 'user_id' => null, 'importance' => 0.7],
        ['kind' => 'preference', 'content' => 'The user prefers short answers.', 'source' => 'extraction', 'user_id' => $user->id, 'importance' => 0.5],
    ]);
});

test('the extractor reads the earlier messages, and a fact may use the user words in them', function (): void {
    MemoryExtractor::fake([['facts' => [
        ['kind' => 'profile', 'content' => 'The user competes in powerlifting in the 88 kg class.', 'subject' => null, 'importance' => 0.6],
    ]]]);

    ExtractMemories::dispatchSync(workspace(), user(), null, '88 kg.', 'Got it.', [
        ['role' => 'user', 'text' => 'I am training for a powerlifting meet.'],
        ['role' => 'assistant', 'text' => 'Nice! What is your weight class?'],
    ]);

    MemoryExtractor::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains("<earlier_messages>\n<user>\nI am training for a powerlifting meet.\n</user>\n\n<assistant>\nNice! What is your weight class?\n</assistant>") && $prompt->contains("<user>\n88 kg.\n</user>"));

    expect(AgentMemory::sole()->content)->toBe('The user competes in powerlifting in the 88 kg class.');
});

test('a fact that only the assistant said in the earlier messages is not grounded', function (): void {
    MemoryExtractor::fake([['facts' => [
        ['kind' => 'profile', 'content' => 'The user competes in powerlifting in the 88 kg class.', 'subject' => null, 'importance' => 0.6],
    ]]]);

    ExtractMemories::dispatchSync(workspace(), user(), null, 'Yes.', 'Got it.', [
        ['role' => 'assistant', 'text' => 'Do you compete in powerlifting in the 88 kg class?'],
    ]);

    expect(AgentMemory::count())->toBe(0);
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

test('extracted free-form subjects are dropped', function (): void {
    MemoryExtractor::fake([['facts' => [
        ['kind' => 'process', 'content' => 'The team deploys on Fridays.', 'subject' => 'deployment schedule', 'importance' => 0.5],
    ]]]);

    ExtractMemories::dispatchSync(workspace(), null, null, 'We deploy on Friday.', 'Noted.');

    expect(AgentMemory::sole()->subject)->toBeNull();
});
