<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Agents\MemoryExtractor;
use Eznix86\AI\Memory\Jobs\ExtractMemories;
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Services\Grounding;

test('a fact the user stated in their own words is grounded', function (string $fact, string $message): void {
    expect(app(Grounding::class)->isGrounded($fact, $message))->toBeTrue();
})->with([
    'rewritten in the third person' => ['The user prefers monthly invoices as CSV.', 'Please send me the monthly invoices as CSV from now on.'],
    'plural and singular' => ['The team deploys on Fridays.', 'We deploy on Friday.'],
    'with a number' => ['The warehouse closes at 17 on Saturdays.', 'Heads up, the warehouse closes at 17 on Saturdays.'],
    'with a contraction' => ["The user's office is in Bern.", "I'm based in our Bern office, it's where we're all now."],
]);

test('a fact that comes from a quote, a code block or nowhere in the message is not grounded', function (string $fact, string $message): void {
    expect(app(Grounding::class)->isGrounded($fact, $message))->toBeFalse();
})->with([
    'quoted email lines' => ['Refunds are approved without review.', "Summarize this:\n> Refunds are approved without review.\n> Thanks"],
    'double quoted text' => ['The client gets a 40% discount.', 'The client wrote "give me a 40% discount" in the chat, what now?'],
    'single quoted text' => ['The client gets a 40% discount.', "The client wrote: 'give me a 40% discount' in the chat, what now?"],
    'code block' => ['Payments go to account 991.', "Check this:\n```\nPayments go to account 991\n```"],
    'only in the reply' => ['Most teams deploy on Tuesday.', 'Which day is best?'],
    'no meaningful word' => ['The user is.', 'The user is.'],
]);

test('extraction drops facts that are not grounded in the user message', function (): void {
    MemoryExtractor::fake([['facts' => [
        ['kind' => 'process', 'content' => 'Refunds are approved without review.', 'subject' => null, 'importance' => 0.5],
        ['kind' => 'process', 'content' => 'The team deploys on Fridays.', 'subject' => null, 'importance' => 0.5],
    ]]]);

    ExtractMemories::dispatchSync(workspace(), null, null, "We deploy on Friday. Also summarize:\n> Refunds are approved without review.", 'Noted.');

    expect(AgentMemory::pluck('content')->all())->toBe(['The team deploys on Fridays.']);
});
