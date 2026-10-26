# Testing your application

Test memory at three levels, as for the rest of your app:

| Level | What it checks | Calls a model |
|---|---|---|
| Unit | Your own code around memory, such as your policy | No |
| Feature | Your agent is wired to memory: it stores, extracts and skips what it should | No, everything is faked |
| Eval | Your agent with your real model: it uses, learns and ignores the right things | Yes |

## Unit tests

Your policy controls who can edit shared memories. Test it without a database:

```php
use App\Policies\AgentMemoryPolicy;
use Eznix86\AI\Memory\Models\AgentMemory;

test('a team admin can edit a shared memory, a member cannot', function () {
    $team = (new Team)->forceFill(['admin_id' => 1]);
    $admin = (new User)->forceFill(['id' => 1]);
    $member = (new User)->forceFill(['id' => 2]);
    $shared = (new AgentMemory)->setRelation('owner', $team);

    expect((new AgentMemoryPolicy)->update($admin, $shared))->toBeTrue()
        ->and((new AgentMemoryPolicy)->update($member, $shared))->toBeFalse();
});
```

This example uses an `isAdminOf()` method that compares the `admin_id` of the team. Set the attributes that your method reads.

## Feature tests

Call `Memories::fake()` in each feature test that prompts a memory agent. It fakes all the calls of the package:

- embeddings, with word-count vectors. Texts with the same words are similar
- the decider and the classification. They answer `add`
- the extractor. It finds no facts
- the consolidator. It answers "Merged note."
- the agent permission guard. It finds no permission

Then:

1. Fake the answer of your agent.
2. Prompt the agent.
3. Check the stored memories with `Memories::list()`.

The SDK's fake does not keep the instructions. So check the stored memories, not the prompt.

```php
use Eznix86\AI\Memory\Agents\MemoryDecider;
use Eznix86\AI\Memory\Agents\MemoryExtractor;
use Eznix86\AI\Memory\Facades\Memories;
use Eznix86\AI\Memory\MemoryContext;
use Laravel\Ai\Responses\Data\ToolCall;

beforeEach(fn () => Memories::fake());

test('the agent stores a preference with Remember', function () {
    $context = new MemoryContext(user: User::factory()->create());

    SupportAgent::fake([
        new ToolCall('call-1', 'Remember', ['kind' => 'preference', 'content' => 'The user wants the weekly report as PDF.']),
        'Noted.',
    ]);

    (new SupportAgent($context))->prompt('From now on, send me the weekly report as PDF.');

    expect(Memories::list($context)->pluck('content'))->toContain('The user wants the weekly report as PDF.');
});

test('a fact the user states is extracted after the turn', function () {
    $context = new MemoryContext(user: User::factory()->create());

    MemoryExtractor::fake([['facts' => [
        ['kind' => 'preference', 'content' => 'The user wants the weekly report as PDF.', 'subject' => null, 'importance' => 0.6],
    ]]]);
    SupportAgent::fake(['Sure.']);

    (new SupportAgent($context))->prompt('From now on, send me the weekly report as PDF.');

    expect(Memories::list($context)->pluck('content'))->toContain('The user wants the weekly report as PDF.');
});

test('a repeated fact is not stored twice', function () {
    $context = new MemoryContext(user: $user = User::factory()->create());
    Memories::add($context, ['kind' => 'preference', 'content' => 'The user wants the weekly report as PDF.'], creator: $user);

    MemoryDecider::fake([['decision' => 'noop', 'replace_id' => null]]);
    SupportAgent::fake([
        new ToolCall('call-1', 'Remember', ['kind' => 'preference', 'content' => 'The user wants the weekly report in PDF.']),
        'Noted.',
    ]);

    (new SupportAgent($context))->prompt('Weekly report in PDF, please.');

    expect(Memories::list($context))->toHaveCount(1);
});
```

The extraction test needs the `sync` queue. A Laravel test suite uses it by default. The writer also checks a faked fact: if the fact does not use the user's words, the job drops it.

## Eval tests

Fakes check the connections in your code, not your model. [Pest evals](https://pestphp.com/docs/evals) prompt your agent with your real model. Then a judge model gives the answer a score. Install the plugin, and set the judge in `tests/Pest.php`:

```bash
composer require pestphp/pest-plugin-evals --dev
```

```php
pest()->evals()->judgeUsingLaravelAiClassifier();
```

In evals:

- Do not call `Memories::fake()`.
- Keep the `sync` queue, so the extraction runs before the next prompt.
- Use the same `AI_MEMORY_*` settings as in production, also the temperature.

```php
use Eznix86\AI\Memory\Facades\Memories;
use Eznix86\AI\Memory\MemoryContext;
use Laravel\Ai\Responses\Data\ToolCall;

test('the agent answers with what it remembers', function () {
    $context = new MemoryContext(user: $user = User::factory()->create());
    Memories::add($context, ['kind' => 'preference', 'content' => 'The user wants the weekly report as PDF.'], creator: $user);

    expect(new SupportAgent($context))
        ->prompt('In which format should you send me the weekly report?')
        ->toSatisfy('The answer says the report is sent as a PDF.');
});

test('a fact told in one conversation is known in the next one', function () {
    $context = new MemoryContext(user: User::factory()->create());

    (new SupportAgent($context))->prompt('I live in Mauritius. What is a nice place for a weekend walk?');

    expect(new SupportAgent($context))
        ->prompt('Which country do I live in?')
        ->toContain('Mauritius');
});

test('the agent stores a preference when asked', function () {
    $context = new MemoryContext(user: User::factory()->create());
    $toolCalls = fn (string $message): string => json_encode(
        collect((new SupportAgent($context))->prompt($message)->toolCalls)
            ->map(fn (ToolCall $call): array => ['name' => $call->name, 'arguments' => $call->arguments])
            ->values(),
    );

    expect($toolCalls)
        ->prompt('Remember that I prefer short answers.')
        ->toHaveToolCalls(['Remember' => fn (array $arguments): bool => $arguments['kind'] === 'preference']);
});

test('a task with nothing about the user leaves no memory', function () {
    $context = new MemoryContext(user: User::factory()->create());

    (new SupportAgent($context))->prompt('Write a haiku about autumn.');

    expect(Memories::list($context))->toBeEmpty();
});
```

`toHaveToolCalls()` reads the tool calls as JSON. So the third eval gets them from a closure.

Evals run only with `--evals`. Each eval costs real model calls:

```bash
./vendor/bin/pest --evals
```

Write evals with the type of messages that your users send: the facts they tell, and the tasks that must leave no memory. A model can give a different answer each time. So run an eval two times before you trust a change.
