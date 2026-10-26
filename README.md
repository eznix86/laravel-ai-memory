# Laravel AI Memory

![Banner](./art/banner.png)

Long-term memory for agents built with the [Laravel AI SDK](https://github.com/laravel/ai). Your agent remembers what a user prefers, how the team works, corrections and notes about customers. Everything is stored in one PostgreSQL table.

Memory is for facts nobody can look up. Invoices, records and statuses belong in a tool.

- [Requirements](#requirements)
- [Add it to your project](#add-it-to-your-project)
- [The memory context](docs/memory-context.md)
- [Testing](#testing)
- [Documentation](#documentation)
- [Migrating from 1.x to 2.x](docs/upgrading.md)

## Requirements

- PHP 8.4+, Laravel 12.62+ or 13.15+
- Laravel AI SDK 1.3+
- PostgreSQL with [pgvector](https://github.com/pgvector/pgvector). The migration creates the extension, so its database user needs that right
- A queue worker

## Add it to your project

**1. Install and migrate.** With Ollama, set `AI_MEMORY_DIMENSIONS` first.

```bash
composer require eznix86/laravel-ai-memory
php artisan migrate
```

**2. Run a queue worker.** Facts are extracted in a job after each turn.

**3. Set a temperature of 0** for the memory model. Without it, some models return no facts.

```bash
AI_MEMORY_PROVIDER_OPTIONS='{"openrouter": {"temperature": 0}}'
```

**4. Add memory to your agent**, next to your own tools and middleware:

```php
use Eznix86\AI\Memory\Contracts\HasMemory;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Middleware\WithMemory;
use Eznix86\AI\Memory\Tools\Forget;
use Eznix86\AI\Memory\Tools\RecallMemory;
use Eznix86\AI\Memory\Tools\Remember;

class SupportAgent implements Agent, HasMemory, HasMiddleware, HasTools
{
    use Promptable;

    public function __construct(
        protected MemoryContext $context,
    ) {}

    public function memory(): MemoryContext
    {
        return $this->context;
    }

    public function tools(): iterable
    {
        return [
            // your tools...
            new Remember($this->context),
            new Forget($this->context),
            new RecallMemory($this->context),
        ];
    }

    public function middleware(): array
    {
        return [
            // your middleware...
            new WithMemory($this->context),
        ];
    }
}
```

**5. Prompt it** with a context for the current user:

```php
$context = new MemoryContext(user: $request->user());

(new SupportAgent($context))->prompt($request->input('message'));
```

The context decides whose memories the agent reads and writes. With a user only, they stay private to that user. Add `owner: $team` to share them with a team. Read [The memory context](docs/memory-context.md) before you go live: it explains owners, users and the kinds of memory.

**6. Schedule the weekly clean-up** in `routes/console.php`:

```php
Schedule::command('memory:consolidate')->weeklyOn(0, '03:00');
```

## Testing

| Level | Checks | Model calls |
|---|---|---|
| Unit | Your code around memory, such as your policy | No |
| Feature | Your agent is wired to memory, with `Memories::fake()` | No |
| Eval | Your agent with your real model, with [Pest evals](https://pestphp.com/docs/evals) | Yes |

```php
// feature test
test('the agent stores a preference with Remember', function () {
    Memories::fake();
    $context = new MemoryContext(user: User::factory()->create());

    SupportAgent::fake([
        new ToolCall('call-1', 'Remember', ['kind' => 'preference', 'content' => 'The user wants the weekly report as PDF.']),
        'Noted.',
    ]);

    (new SupportAgent($context))->prompt('From now on, send me the weekly report as PDF.');

    expect(Memories::list($context)->pluck('content'))->toContain('The user wants the weekly report as PDF.');
});

// eval test, run with ./vendor/bin/pest --evals
test('a fact told in one conversation is known in the next one', function () {
    $context = new MemoryContext(user: User::factory()->create());

    (new SupportAgent($context))->prompt('I live in Mauritius. What is a nice place for a weekend walk?');

    expect(new SupportAgent($context))
        ->prompt('Which country do I live in?')
        ->toContain('Mauritius');
});
```

All examples: [docs/testing.md](docs/testing.md).

## Documentation

- [How it works](docs/how-it-works.md): one turn, and the path of a fact through the writer
- [The memory context](docs/memory-context.md)
  - [Owners, users and kinds](docs/memory-context.md#the-memory-context)
  - [Tools, middleware and skill](docs/memory-context.md#tools-middleware-and-skill)
- [Safety, duplicates and decay](docs/safety.md)
  - [What the writer rejects](docs/safety.md#safety)
  - [Permission guard](docs/safety.md#permission-guard)
  - [Duplicates and corrections](docs/safety.md#duplicates-and-corrections)
  - [Decay and consolidation](docs/safety.md#decay-and-consolidation)
- [Managing memories](docs/managing-memories.md)
  - [The API for a memory page](docs/managing-memories.md#managing-memories)
  - [Adding memories from your application](docs/managing-memories.md#adding-memories-from-your-application)
  - [Who can edit memories](docs/managing-memories.md#who-can-edit-memories)
- [Languages](docs/languages.md)
  - [What works in any language](docs/languages.md#what-works-in-any-language)
  - [What you set for your languages](docs/languages.md#what-you-set-for-your-languages)
  - [Translating the messages](docs/languages.md#translating-the-messages)
  - [Prompts in another language](docs/languages.md#prompts-in-another-language)
- [Customizing](docs/customizing.md)
  - [Models](docs/customizing.md#models)
  - [Prompts and messages](docs/customizing.md#customizing-prompts-and-messages)
  - [Replacing classes](docs/customizing.md#extending-the-package)
  - [All settings](docs/customizing.md#configuration)
- [Testing your application](docs/testing.md)
  - [Unit tests](docs/testing.md#unit-tests)
  - [Feature tests](docs/testing.md#feature-tests)
  - [Eval tests](docs/testing.md#eval-tests)
- [Migrating from 1.x to 2.x](docs/upgrading.md)
- [Contributing](CONTRIBUTING.md)

## License

MIT. See [LICENSE](LICENSE).
