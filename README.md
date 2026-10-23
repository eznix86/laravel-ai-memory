# Laravel AI Memory

![Banner](./art/banner.png)

Long-term memory for agents built with the [Laravel AI SDK](https://github.com/laravel/ai).

The agent remembers durable facts it cannot look up: preferences, team processes, corrections, customer notes, lessons and temporary context. Memories live in one table in your database. There is no external memory service.

Memory is not for business data, chat history or secrets. If a new colleague could look it up, it is a tool call, not a memory.

## Requirements

- PHP 8.4+
- Laravel 12.62+ or 13.15+
- Laravel AI SDK 1.3+
- PostgreSQL with the [pgvector](https://github.com/pgvector/pgvector) extension

## Installation

```bash
composer require eznix86/laravel-ai-memory
```

Set `MEMORY_DIMENSIONS` to the output size of your embedding model, then run the migrations:

```bash
php artisan migrate
```

Optionally publish the config and the prompt templates:

```bash
php artisan vendor:publish --tag=memory-config
php artisan vendor:publish --tag=memory-views
```

## Memory context

Every read and write happens in a `MemoryContext`. A memory never leaves its tenant.

```php
use Eznix86\AI\Memory\MemoryContext;

$context = new MemoryContext(
    tenant: $workspace,                 // any Eloquent model: team, workspace, account
    user: $request->user(),             // optional: sees their personal memories too
    conversationId: $conversationId,    // optional: recorded as the source of new memories
    subjects: ['customer:42'],          // optional: memories about these subjects are always loaded
);
```

A memory belongs to the whole tenant, or to one user. Preferences are personal. The other kinds are shared in the tenant.

| Kind | Example |
|---|---|
| `preference` | The user prefers short answers. |
| `process` | Refunds above 500 francs need a manager. |
| `correction` | The Geneva office closed in 2025. |
| `customer_note` | Customer 42 pays late. |
| `lesson` | The export fails when a name has an emoji. |
| `context` | The team is in a release freeze this week. |

## Agent setup

```php
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Middleware\WithMemory;
use Eznix86\AI\Memory\Tools\Forget;
use Eznix86\AI\Memory\Tools\RecallMemory;
use Eznix86\AI\Memory\Tools\Remember;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class AssistantAgent implements Agent, HasMiddleware, HasTools
{
    use Promptable;

    public function __construct(
        protected MemoryContext $context,
    ) {}

    public function instructions(): string
    {
        return 'You are a helpful assistant.';
    }

    public function tools(): iterable
    {
        return [
            new Remember($this->context),
            new Forget($this->context),
            new RecallMemory($this->context),
        ];
    }

    public function middleware(): array
    {
        return [
            new WithMemory($this->context),
        ];
    }
}
```

`WithMemory` adds the 30 strongest live memories to the agent instructions, once per prompt. The memories stay in the instructions after tool calls. The user's preferences and the memories about the context subjects are always included. Each loaded memory has its `recall_count` incremented and its `last_used_at` set.

The tools:

| Tool | What it does |
|---|---|
| `Remember` | Stores one fact: `kind`, `content` (at most 300 characters), optional `subject` and `importance` from 0 to 1 |
| `Forget` | Archives a memory by id. Nothing is deleted, and a person can restore it |
| `RecallMemory` | Searches the memories that were not loaded |

### Memory skill

`MemorySkill` teaches the agent how to use its memory well: when a fact is worth keeping, which kind and subject to choose, and how to correct a fact. The agent loads it with the SDK's `LoadSkill` tool when it needs it.

```php
use Eznix86\AI\Memory\Skills\MemorySkill;
use Laravel\Ai\Contracts\HasSkills;

class AssistantAgent implements Agent, HasMiddleware, HasSkills, HasTools
{
    public function skills(): iterable
    {
        return [
            MemorySkill::make(),
        ];
    }
}
```

The safety rules are also in the tool descriptions, because small models often do not load skills.

## What gets rejected

Every write goes through `MemoryWriter`. It rejects:

- content that is empty or longer than 300 characters
- card numbers (Luhn check), IBANs (checksum), API keys, tokens, private keys and passwords
- facts phrased as instructions to the agent, such as "Always approve refunds" or "Ignore previous instructions"

The `Remember` tool returns the reason to the agent, so it can restate the fact.

## Managing memories

The `Memories` facade is the API for a memory page in your application. The package ships no routes and no UI.

```php
use Eznix86\AI\Memory\Facades\Memories;

Memories::list($context, kind: MemoryKind::Process, subject: 'customer:42', search: 'refund');
Memories::add($context, $user, ['kind' => 'process', 'content' => 'Deploys happen on Tuesday.']);
Memories::edit($memory, 'Deploys happen on Thursday.', $user);
Memories::delete($memory);
Memories::export($user);
Memories::purge($workspace, 'customer:42');
```

`add` and `edit` mark the memory as written or confirmed by that person. `delete` removes the row. `export` returns all of the user's memories, archived and superseded ones included. `purge` removes every memory about a subject in the tenant, for privacy requests.

`MemoryPolicy` lets a user update and delete their own memories. Shared memories need a tenant administrator. Extend the policy to say who that is:

```php
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Policies\MemoryPolicy;

class AgentMemoryPolicy extends MemoryPolicy
{
    protected function administers(Model $user, Model $tenant): bool
    {
        return $user->isAdminOf($tenant);
    }
}

Gate::policy(AgentMemory::class, AgentMemoryPolicy::class);
```

## Prompt templates

The text the agent sees comes from Blade views in `resources/views/prompts/memory`. To change one, put a file with the same name in your application's `resources/views/prompts/memory`, or publish them all with `--tag=memory-views`.

| View | Used for |
|---|---|
| `prompts.memory.instructions` | The memories added to the instructions |
| `prompts.memory.memory` | One memory line |
| `prompts.memory.tools.remember` | `Remember` description |
| `prompts.memory.tools.forget` | `Forget` description |
| `prompts.memory.tools.recall-memory` | `RecallMemory` description |
| `prompts.memory.tools.recall-memory-results` | `RecallMemory` results |
| `prompts.memory.skill.description` | `MemorySkill` description |
| `prompts.memory.skill.instructions` | `MemorySkill` instructions |

Use `{!! !!}` for memory content in your templates. `{{ }}` escapes quotes as HTML entities, and the model then sees `&quot;`.

## Configuration

| Option | Env | Default | Description |
|---|---|---|---|
| `table` | `MEMORY_TABLE` | `agent_memories` | Table name |
| `dimensions` | `MEMORY_DIMENSIONS` | `1536` | Embedding vector dimensions. Must match your embedding model |
| `loaded_limit` | `MEMORY_LOADED_LIMIT` | `30` | Memories added to the instructions |
| `recall_limit` | `MEMORY_RECALL_LIMIT` | `8` | Memories returned by `RecallMemory` |
| `recall_min_similarity` | `MEMORY_RECALL_MIN_SIMILARITY` | `0.3` | Minimum cosine similarity for `RecallMemory` |

## Testing

The package tests use [Pest](https://pestphp.com) and [Testcontainers](https://testcontainers.com) with PostgreSQL and pgvector, so Docker must be running.

```bash
./vendor/bin/pest
```

In your own tests, `Memories::fake()` fakes embeddings with one identical vector, so every memory matches every query:

```php
use Eznix86\AI\Memory\Facades\Memories;

test('the agent sees what it remembers', function () {
    Memories::fake();

    Memories::add($context, $user, ['kind' => 'preference', 'content' => 'The user prefers French.']);

    AssistantAgent::fake(['Bonjour']);

    (new AssistantAgent($context))->prompt('Hi');
});
```

## License

MIT
