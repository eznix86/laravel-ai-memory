# Laravel AI Memory

![Banner](./art/banner.png)

Long-term memory for agents of the [Laravel AI SDK](https://github.com/laravel/ai), in one PostgreSQL table.

The agent keeps what users tell it about themselves and their work: who they are, what they like, what they work on and how their team works.

- [Requirements](#requirements)
- [Getting started](#getting-started)
- [The memory context](docs/memory-context.md)
- [Injecting memory](#injecting-memory)
- [Languages](#languages)
- [Testing](#testing)
- [Extending the package](#extending-the-package)
- [Migrating from 1.x to 2.x](docs/upgrading.md)

## Requirements

- PHP 8.4+, Laravel 12.62+ or 13.15+
- Laravel AI SDK 1.3+
- PostgreSQL with [pgvector](https://github.com/pgvector/pgvector). The migration adds the extension, so the database user must have that permission
- A queue worker
- An LLM (text model) with structured output, required
  - extracts the facts after each turn
  - decides on duplicates
  - merges memories each week
- An embedding model, required
  - stores a vector for each fact
  - finds the memories for each prompt
  - searches with `RecallMemory`
- A classification model, optional
  - only with `AI_MEMORY_DECIDER=classification` or `AI_MEMORY_PERMISSION_GUARD=classification`
- An LLM (text model) or a TypeSafe System One model, for evals only
  - judges the answers in your Pest evals

See [Models](docs/customizing.md#models).

## Getting started

**1. Install and migrate.** If you use Ollama, set `AI_MEMORY_DIMENSIONS` first.

```bash
composer require eznix86/laravel-ai-memory
php artisan migrate
```

**2. Run a queue worker.** After each turn, a job extracts the facts.

**3. Set the temperature of the memory model to 0.** If you do not, some models return no facts.

```bash
AI_MEMORY_PROVIDER_OPTIONS='{"openrouter": {"temperature": 0}}'
```

**4. Add memory to your agent.** Keep your own tools and middleware:

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

**5. Prompt the agent** with a context for the current user:

```php
$context = new MemoryContext(user: $request->user());

(new SupportAgent($context))->prompt($request->input('message'));
```

Each user gets their own memories. To share memories with a team, add `owner: $team`. See [The memory context](docs/memory-context.md).

**6. Schedule the weekly clean-up** in `routes/console.php`. It merges memories on the same subject and archives the ones that faded:

```php
Schedule::command('memory:consolidate')->sundays()->at('02:00');
```

See [Decay and consolidation](docs/safety.md#decay-and-consolidation).

## Injecting memory

Your app can also give facts to the agent, for example from a settings page or an app event:

```php
use Eznix86\AI\Memory\Facades\Memories;
use Eznix86\AI\Memory\MemoryContext;

class RememberTravelMode
{
    public function handle(TravelModeEnabled $event): void
    {
        Memories::add(new MemoryContext(user: $event->user), [
            'kind' => 'context',
            'content' => "The user is travelling in {$event->country} until {$event->until->format('j F')}.",
            'source' => 'travel-mode',
            'expires_at' => $event->until,
        ]);
    }
}
```

The memory ends at `expires_at`. Add `creator: $admin` when a person writes it. See [Adding memories from your application](docs/managing-memories.md#adding-memories-from-your-application).

## Languages

Facts stay in the language of the user. For other languages, use a multilingual embedding model, and translate the short messages that the agent reads:

```bash
php artisan vendor:publish --tag=memory-lang
```

Then copy `lang/vendor/memory/en` to a folder for your language, and translate the lines. See [Languages](docs/languages.md).

## Testing

| Level | Checks | Model calls |
|---|---|---|
| Unit | Your code for memory, such as your policy | No |
| Feature | The connection of your agent to memory, with `Memories::fake()` | No |
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

For all the examples, see [docs/testing.md](docs/testing.md).

## Extending the package

- [How it works](docs/how-it-works.md): one turn, and the path of a fact through the writer
- [The memory context](docs/memory-context.md)
  - [Owners and users](docs/memory-context.md#the-memory-context)
  - [Kinds](docs/memory-context.md#kinds)
  - [Subjects](docs/memory-context.md#subjects)
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
