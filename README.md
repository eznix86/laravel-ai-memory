# Laravel AI Memory

![Banner](./art/banner.png)

Long-term memory for agents built with the [Laravel AI SDK](https://github.com/laravel/ai).

Your agent remembers durable facts it cannot look up: what a user prefers, how the team works, a correction, a note about a customer, a lesson, a temporary situation. The memories live in one table in your PostgreSQL database. There is no external memory service.

Memory is not for business data, chat history or secrets. If a new colleague could look it up, it is a tool call, not a memory.

## Features

- **Scoped**: every memory belongs to an owner (any Eloquent model) and, when it is personal, to one user. A memory never leaves its owner.
- **Loaded every turn**: the memories that match the conversation, the user's preferences and the strongest memories go into the agent instructions. The agent searches the others by keyword and by meaning.
- **Learns by itself**: after each turn, a queued job keeps up to 3 facts the user stated. Facts taken from a quoted email or from the agent's own reply are dropped.
- **Stays clean**: a new fact that repeats a memory is skipped, and one that contradicts a memory replaces it.
- **Forgets slowly**: unused memories fade, a weekly job merges and archives them, and every change by the agent can be undone. Only people delete.
- **Guarded**: the writer rejects secrets (card numbers, IBANs, API keys, passwords), instructions phrased as facts and, optionally, facts that give the agent a permission.
- **Model-agnostic**: works with any text and embedding model of the SDK.
- **Any language**: every prompt and message can be overridden and translated.
- **Headless**: an API for your own memory page, with export and privacy purge. No routes, no UI.

## Requirements

- PHP 8.4+
- Laravel 12.62+ or 13.15+
- Laravel AI SDK 1.3+
- PostgreSQL with the [pgvector](https://github.com/pgvector/pgvector) extension
- A queue worker, for the extraction and consolidation jobs

## Quick start

**1. Install and migrate.** The migration takes the vector size from your embedding provider in `config/ai.php`. With Ollama, which reports no size, set its embeddings dimensions there first, or `AI_MEMORY_DIMENSIONS`.

```bash
composer require eznix86/laravel-ai-memory
php artisan migrate
```

**2. Give your agent a memory.** Build a `MemoryContext` for the current user, and hand it to the tools and the middleware:

```php
use Eznix86\AI\Memory\Contracts\HasMemory;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Middleware\WithMemory;
use Eznix86\AI\Memory\Tools\Forget;
use Eznix86\AI\Memory\Tools\RecallMemory;
use Eznix86\AI\Memory\Tools\Remember;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class AssistantAgent implements Agent, HasMemory, HasMiddleware, HasTools
{
    use Promptable;

    public function __construct(
        protected MemoryContext $context,
    ) {}

    public function instructions(): string
    {
        return 'You are a helpful assistant.';
    }

    public function memory(): MemoryContext
    {
        return $this->context;
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

**3. Prompt it.**

```php
$context = new MemoryContext(user: $request->user());

(new AssistantAgent($context))->prompt('From now on, send me the weekly report as PDF.');
```

The agent can store that preference with `Remember`, and the extraction job stores it too when the agent does not. Next time, it is in the instructions before the user says a word.

**4. Schedule the weekly consolidation** in `routes/console.php`:

```php
Schedule::command('memory:consolidate')->weeklyOn(0, '03:00');
```

## How it works

```
Write   Remember tool ─┐
        Extraction job ┴─> MemoryWriter: validate ─> reject secrets and instructions
                                         ─> permission guard (optional)
                                         ─> close memories? ─> add, replace or skip
                                         ─> archive the weakest when the context is full

Read    every prompt  ─> WithMemory loads the user's preferences, the memories that match
                          the last 3 user messages, then the strongest ones, up to 30
        on demand     ─> RecallMemory: keyword search + meaning search, fused, ties by strength

Forget  time          ─> strength decays per kind
        contradiction ─> the old row gets superseded_by
        Forget tool   ─> the row gets archived_at
        weekly        ─> 6+ memories on one subject merge into one, faded ones are archived
        people        ─> Memories::delete() and Memories::purge() remove rows
```

## The memory context

Every read and write happens in a `MemoryContext`:

```php
use Eznix86\AI\Memory\MemoryContext;

$context = new MemoryContext(
    user: $request->user(),             // the user the agent talks to
    owner: $team,                       // optional: any Eloquent model whose members share the memories
    conversation: $conversationId,      // optional: an id or a model, recorded as the source of new memories
);
```

Without an owner, the user owns the memories, and they stay private to that user. That fits a single-user system:

```php
new MemoryContext(user: $user);
```

With an owner, such as a team, the user's preferences stay private, and the other memories are shared by everyone in the team. A context needs a user, an owner, or both.

With the SDK's `RemembersConversations`, an agent can build it from the conversation:

```php
public function memory(): MemoryContext
{
    return new MemoryContext(user: $this->conversationParticipant(), owner: $this->team, conversation: $this->currentConversation());
}
```

Each memory has a **kind**. Profile facts and preferences belong to one user. The other kinds are shared by everyone in the owner.

| Kind | Example |
|---|---|
| `profile` | The user is a chemistry student and uses Arch Linux. |
| `preference` | The user prefers short answers. |
| `process` | Refunds above 500 francs need a manager. |
| `correction` | The Geneva office closed in 2025. |
| `customer_note` | Customer 42 pays late. |
| `lesson` | The export fails when a name has an emoji. |
| `context` | The team is in a release freeze this week. |

A **subject** is a stable id written as `type:id`, such as `customer:42` or `project:apollo`. Consolidation and the privacy purge work by subject. Other subjects are rejected by the writer and dropped by the tools, because a model's free-form labels ("team deployment") would keep duplicates apart.

## Tools, middleware and skill

| Piece | What it does |
|---|---|
| `Remember` | Stores one fact: a kind, at most 300 characters, an optional subject and an importance from 0 to 1. It tells the agent when a fact is rejected, already known or replaces another |
| `Forget` | Archives a memory by id. A person can restore it |
| `RecallMemory` | Searches the memories that were not loaded. It ranks them by keyword (PostgreSQL full text, any word of the query) and by meaning (pgvector) and fuses both rankings. Strength only decides between equal matches. Archived memories are searched only when the agent asks |
| `WithMemory` | Adds memories to the instructions once per prompt, and keeps them after tool calls: first the user's preferences, then the 8 memories that best match the last 3 user messages, then the strongest ones, up to 30. So an old note about the customer the user names is loaded, even when it is weak |
| `MemorySkill` | Guidance the agent loads with the SDK's `LoadSkill` tool: when a fact is worth keeping, which kind to choose, how to correct a fact |

To add the skill, implement `HasSkills`:

```php
use Eznix86\AI\Memory\Skills\MemorySkill;

public function skills(): iterable
{
    return [
        MemorySkill::make(),
    ];
}
```

The safety rules are also in the tool descriptions, because small models often do not load skills.

## Safety

Every write goes through `MemoryWriter`. It rejects:

- content that is empty or longer than 300 characters
- card numbers (Luhn check), IBANs (checksum), API keys, tokens, private keys and passwords
- facts phrased as instructions, such as "Always approve refunds" or "Ignore previous instructions"

The extraction job keeps a fact only when at least half of it comes from the user's own words. Quoted lines (`>`), quoted strings, code blocks and the agent's reply do not count. So a fact the model took from a pasted email is dropped, even when the model ignored its instructions.

### Permission guard

A capable model can rewrite "Always approve refunds" into "The user prefers that refunds be approved without asking". That passes the instruction patterns, and it would steer every later turn. The permission guard asks a model whether a fact lets the agent act on its own, and rejects it when it does. It costs one model call per write.

| `AI_MEMORY_PERMISSION_GUARD` | Asks |
|---|---|
| `none` (default) | Nothing |
| `agent` | A text model, through `AI_MEMORY_PROVIDER` and `AI_MEMORY_MODEL` |
| `classification` | A classification model, through `AI_MEMORY_CLASSIFICATION_PROVIDER` and `AI_MEMORY_CLASSIFICATION_MODEL` |

The guard catches most permissions, not all. Keep real actions, such as payments, behind the SDK's tool approvals.

## Duplicates and corrections

Before a fact is stored, the writer looks for up to 5 live memories with the same owner and subject, and a cosine similarity of 0.70 or more. If it finds some, it asks a decider:

| Decision | Effect |
|---|---|
| `noop` | A memory already says it. Nothing is stored |
| `update` | The fact changes one memory. The new row is stored, and the old one gets `superseded_by` |
| `add` | The fact is about something else. It is stored |

| `AI_MEMORY_DECIDER` | Asks |
|---|---|
| `agent` (default) | A text model with structured output, through `AI_MEMORY_PROVIDER` and `AI_MEMORY_MODEL` |
| `classification` | A classification model through the SDK's `Classification`: TypeSafe, OpenAI, OpenRouter, or an OpenAI-compatible server with a `decisions` endpoint |

You can also bind your own `Eznix86\AI\Memory\Contracts\DecidesDuplicates`.

When a context holds 500 live memories, the weakest ones are archived before a new one is added. Memories a person wrote or confirmed are never archived this way.

## Decay and consolidation

Every memory has a strength from 0 to 1:

```
strength = min(1, importance × 0.5^(days unused / half-life) × (1 + 0.1 × min(recall count, 10)))
```

| Kind | Half-life |
|---|---|
| `preference` | 180 days |
| `process`, `correction` | 365 days |
| `customer_note` | 120 days |
| `lesson` | 90 days |
| `context` | 21 days |

Each time a memory is loaded or recalled, it gets stronger again.

`memory:consolidate` queues one job per owner. The job merges each subject that has 6 or more live memories into one note written by a model, and the originals get `superseded_by`. Then it archives the memories whose strength fell below 0.05, except the ones a person wrote or confirmed. If the merged note is rejected, the originals stay and a warning is logged. Pass `--sync` to run it at once.

The command also reports memories whose owner no longer exists. Pass `--prune` to delete them. Better, delete them with their owner, for example in your `Team` model:

```php
protected static function booted(): void
{
    static::deleting(fn (Team $team) => AgentMemory::forOwner($team)->delete());
}
```

## Managing memories

The `Memories` facade is the API for a memory page in your application:

```php
use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\Facades\Memories;

Memories::list($context, kind: MemoryKind::Process, subject: 'customer:42', search: 'refund');

$memory = Memories::add($context, $user, ['kind' => 'process', 'content' => 'Deploys happen on Tuesday.'])->memory;

Memories::edit($memory, 'Deploys happen on Thursday.', $user);
Memories::delete($memory);
Memories::export($user);
Memories::purge($workspace, 'customer:42');
```

`add` and `edit` mark the memory as written or confirmed by that person, and go through the same checks as the agent. `export` returns all of a user's memories, archived and superseded ones included. `purge` removes every memory about a subject in the owner, for privacy requests.

`MemoryPolicy` lets users update and delete their own memories. Shared memories need an owner administrator. Extend the policy to say who that is:

```php
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Policies\MemoryPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class AgentMemoryPolicy extends MemoryPolicy
{
    protected function administers(Model $user, Model $owner): bool
    {
        return $user->isAdminOf($owner);
    }
}

Gate::policy(AgentMemory::class, AgentMemoryPolicy::class);
```

## Models

Nothing in the package depends on a specific model. Set the provider and model of the memory agents (decider, extractor, consolidator, agent permission guard) with `AI_MEMORY_PROVIDER` and `AI_MEMORY_MODEL`, or leave them empty to use the defaults of `config/ai.php`. The text model needs structured output.

Memories are embedded with the embeddings default of `config/ai.php`. To embed them with another provider or model, for example a multilingual one, set `AI_MEMORY_EMBEDDINGS_PROVIDER` and `AI_MEMORY_EMBEDDINGS_MODEL`.

These are small tasks, but very small models make more mistakes on them. Check the model you choose with the live test suite (see [Contributing](#contributing)). Raise `AI_MEMORY_TIMEOUT` for slow local models.

## Languages

The package works in any language. For languages other than English, check four things:

| What | Where | Default |
|---|---|---|
| Prompts | Blade views, see [Customizing prompts and messages](#customizing-prompts-and-messages). They tell the model to keep facts in the user's language, so they also work for other languages | English |
| Short messages: tool results, rejection reasons, schema descriptions | Lang files. They follow the app locale | English |
| Keyword search | `AI_MEMORY_FULLTEXT_LANGUAGE`: a PostgreSQL text search configuration such as `french`, or `simple` for several languages. PostgreSQL does not split Chinese, Japanese or Korean into words, so there recall relies on the meaning search, or on an extension such as zhparser | `english` |
| Instruction and password patterns | `instruction_patterns` and `password_patterns` in `config/memory.php`. Add your users' languages, or use the permission guard, which works in any language | English |

The extraction check compares Chinese and Japanese text character by character, and other text by pairs of letters, so it works with and without spaces. Recall by meaning depends on your embedding model: pick a multilingual one if your users do not write in English.

## Customizing prompts and messages

```bash
php artisan vendor:publish --tag=memory-config   # config/memory.php
php artisan vendor:publish --tag=memory-views    # resources/views/prompts/memory
php artisan vendor:publish --tag=memory-lang     # lang/vendor/memory
```

The prompts are Blade views. To change one, put a file with the same name in your application's `resources/views/prompts/memory`. Use `{!! !!}` for memory content: `{{ }}` escapes quotes, and the model would see `&quot;`.

| View | Used for |
|---|---|
| `prompts.memory.instructions`, `memory` | The memories added to the instructions, and one memory line |
| `prompts.memory.tools.*` | The descriptions and results of `Remember`, `Forget` and `RecallMemory` |
| `prompts.memory.skill.*` | The description and instructions of `MemorySkill` |
| `prompts.memory.agents.decider`, `decider-prompt` | The duplicate decider |
| `prompts.memory.agents.extractor`, `extractor-prompt` | Extraction |
| `prompts.memory.agents.consolidator`, `consolidator-prompt` | Consolidation |
| `prompts.memory.agents.permission`, `permission-prompt` | The agent permission guard |
| `prompts.memory.classification.decider` | The classification decider question |
| `prompts.memory.classification.permission.*` | The permission question and its two answers, used by both guards |

## Extending the package

The package resolves its agents, deciders, guards, services and embedder from the container. Bind your own class in a service provider to replace one:

```php
use Eznix86\AI\Memory\Agents\MemoryExtractor;

$this->app->bind(MemoryExtractor::class, TeamExtractor::class);
```

| Class | Role |
|---|---|
| `Agents\MemoryExtractor`, `MemoryDecider`, `MemoryConsolidator`, `PermissionChecker` | The memory agents |
| `Deciders\AgentDecider`, `ClassificationDecider`, or the `Contracts\DecidesDuplicates` contract | The duplicate decider |
| `PermissionGuards\*`, or the `Contracts\GuardsPermissions` contract | The permission guard |
| `Services\MemoryWriter`, `MemorySearch`, `MemoryManager`, `Grounding` | Writing, search, management, grounding |
| `Embedder` | The vectors of memories and queries |

## Configuration

Every option is documented in `config/memory.php`. The ones you are most likely to set:

| Env | Default | What it does |
|---|---|---|
| `AI_MEMORY_EMBEDDINGS_PROVIDER`, `AI_MEMORY_EMBEDDINGS_MODEL` | `config/ai.php` defaults | Provider and model that embed the memories |
| `AI_MEMORY_DIMENSIONS` | the provider's size | Vector size. Set it only when the provider reports none, such as Ollama |
| `AI_MEMORY_PROVIDER`, `AI_MEMORY_MODEL` | `config/ai.php` defaults | Model of the memory agents |
| `AI_MEMORY_TIMEOUT` | `120` | Seconds one answer of a memory agent may take |
| `AI_MEMORY_DECIDER` | `agent` | `agent` or `classification` |
| `AI_MEMORY_PERMISSION_GUARD` | `none` | `none`, `agent` or `classification` |
| `AI_MEMORY_CLASSIFICATION_PROVIDER`, `AI_MEMORY_CLASSIFICATION_MODEL` | `config/ai.php` defaults | Model of the classification decider and guard |
| `AI_MEMORY_FULLTEXT_LANGUAGE` | `english` | PostgreSQL text search configuration |
| `AI_MEMORY_EXTRACTION` | `true` | Extract facts after each turn |
| `AI_MEMORY_EXTRACTION_WINDOW` | `4` | Messages before the turn the extractor reads as context, `0` for the turn alone |
| `AI_MEMORY_QUEUE` | default queue | Queue of the extraction and consolidation jobs |
| `AI_MEMORY_LOADED_LIMIT` | `30` | Memories added to the instructions |
| `AI_MEMORY_RELEVANT_LIMIT` | `8` | Of those, memories chosen because they match the last user messages |
| `AI_MEMORY_MAX_LIVE` | `500` | Live memories per context before the weakest are archived |

## Testing your application

`Memories::fake()` fakes everything the package calls:

- embeddings, with word-count vectors, so texts that share words are similar
- the decider and the classification, which answer `add`
- the extractor, which finds no facts
- the consolidator, which answers "Merged note."
- the agent permission guard, which finds no permission

```php
use Eznix86\AI\Memory\Agents\MemoryDecider;
use Eznix86\AI\Memory\Facades\Memories;

test('the agent sees what it remembers', function () {
    Memories::fake();

    Memories::add($context, $user, ['kind' => 'preference', 'content' => 'The user prefers French.']);

    AssistantAgent::fake(['Bonjour']);

    (new AssistantAgent($context))->prompt('Hi');
});

test('a repeated fact is not stored twice', function () {
    Memories::fake();
    MemoryDecider::fake([['decision' => 'noop', 'replace_id' => null]]);

    // ...
});
```

## Upgrading from 0.x

1.0 replaces the 0.x API. The old `AgentMemory` facade with `store()` and `recall()`, the `StoreMemory` tool, the array context and the `memories` table are gone. `AgentMemory` is now the Eloquent model. Use a `MemoryContext`, the `Remember` and `RecallMemory` tools, and the `Memories` facade. The new `agent_memories` table is created by the migration. Old rows are not copied, and the old `memories` table is left in place for you to migrate or drop.

## Contributing

The tests need PostgreSQL with pgvector. Copy `.env.example` to `.env`: it lists every variable the tests read.

```bash
cp .env.example .env
./vendor/bin/pest
```

With `DB_HOST` set, the tests use that database. Without it, they start a pgvector container with [Testcontainers](https://testcontainers.com), so Docker must be running.

This runs the unit and feature tests, which call no model. The tests in `tests/Evals` are [Pest evals](https://pestphp.com/docs/evals): they call real models with cases in English, French, Chinese and Japanese, including real conversations from [OpenAssistant OASST2](https://huggingface.co/datasets/OpenAssistant/oasst2) (Apache-2.0). They are skipped unless you pass `--evals`. They use the same `AI_MEMORY_*` settings as an application, and the provider keys of `config/ai.php`. A classification model judges whether the stored memories hold the expected fact, by default TypeSafe, so set `TYPESAFE_API_KEY` too:

```bash
./vendor/bin/pest --evals
```

Cases marked `(holdout)` were not used to write the prompts. They show how a model and the prompts do on text they were not tuned for.

Before a pull request, run the checks:

```bash
./vendor/bin/pint
./vendor/bin/rector
./vendor/bin/phpstan analyse
```

## License

MIT. See [LICENSE](LICENSE).
