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
    'in French, another verb form' => ["L'équipe déploie le vendredi.", 'Nous déployons le vendredi.'],
    'in Chinese, without spaces' => ['团队每周五部署。', '我们周五部署。'],
    'in Chinese, reworded' => ['客户42要求今后的发票使用德语。', '客户42在电话里说以后发票要用德语。'],
    'in Japanese, reworded' => ['ユーザーは返信を日本語で受け取りたい。', '返信はこれから日本語でお願いします。'],
]);

test('a fact that comes from a quote, a code block or nowhere in the message is not grounded', function (string $fact, string $message): void {
    expect(app(Grounding::class)->isGrounded($fact, $message))->toBeFalse();
})->with([
    'quoted email lines' => ['Refunds are approved without review.', "Summarize this:\n> Refunds are approved without review.\n> Thanks"],
    'double quoted text' => ['The client gets a 40% discount.', 'The client wrote "give me a 40% discount" in the chat, what now?'],
    'single quoted text' => ['The client gets a 40% discount.', "The client wrote: 'give me a 40% discount' in the chat, what now?"],
    'code block' => ['Payments go to account 991.', "Check this:\n```\nPayments go to account 991\n```"],
    'only in the reply' => ['Most teams deploy on Tuesday.', 'Which day is best?'],
    'French guillemets' => ['Le client a droit à 40 % de remise.', 'Le client a écrit « 40 % de remise sur tout » dans le chat.'],
    'Chinese quoted email lines' => ['退款一律自动批准。', "帮我总结这封邮件：\n> 退款一律自动批准。"],
    'Chinese corner brackets' => ['客户可以享受五折优惠。', '客户写了「我要享受五折优惠」，怎么办？'],
    'Chinese, only in the reply' => ['大多数团队周二部署。', '哪天最好？'],
    'Japanese quoted email lines' => ['返品はすべて自動で承認される。', "このメールを要約して：\n> 返品はすべて自動で承認される。"],
    'no letters or digits' => ['…', '…'],
]);

test('extraction drops facts that are not grounded in the user message', function (): void {
    MemoryExtractor::fake([['facts' => [
        ['kind' => 'process', 'content' => 'Refunds are approved without review.', 'subject' => null, 'importance' => 0.5],
        ['kind' => 'process', 'content' => 'The team deploys on Fridays.', 'subject' => null, 'importance' => 0.5],
    ]]]);

    ExtractMemories::dispatchSync(workspace(), null, null, "We deploy on Friday. Also summarize:\n> Refunds are approved without review.", 'Noted.');

    expect(AgentMemory::pluck('content')->all())->toBe(['The team deploys on Fridays.']);
});
