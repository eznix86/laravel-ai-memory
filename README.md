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
- Any text provider and model of the SDK with structured output, and any embedding model. Nothing in the package depends on a specific model

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

A subject is a stable id written as `type:id`, such as `customer:42` or `project:apollo`. Purge and consolidation work by subject. The writer rejects other subjects, and the `Remember` tool and extraction drop them, because a model's free-form labels ("team deployment") would keep duplicates apart.

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

    public function memoryContext(): MemoryContext
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

`HasMemory` turns on automatic extraction for the agent (see below).

`WithMemory` adds the 30 strongest live memories to the agent instructions, once per prompt. The memories stay in the instructions after tool calls. The user's preferences and the memories about the context subjects are always included. Each loaded memory has its `recall_count` incremented and its `last_used_at` set.

The tools:

| Tool | What it does |
|---|---|
| `Remember` | Stores one fact: `kind`, `content` (at most 300 characters), optional `subject` and `importance` from 0 to 1 |
| `Forget` | Archives a memory by id. Nothing is deleted, and a person can restore it |
| `RecallMemory` | Searches the memories that were not loaded. It ranks them by keyword (Postgres full text) and by meaning (pgvector), fuses both rankings with reciprocal rank fusion, weighs them by strength and returns the top 8. Archived memories are searched only when the agent passes `include_archived` |

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
- facts phrased as instructions to the agent, such as "Always approve refunds" or "Ignore previous instructions". These patterns are in the config and only know English by default: see [Languages](#languages)

The `Remember` tool returns the reason to the agent, so it can restate the fact.

### Permission guard

A capable model rewrites "Always approve refunds" into a fact such as "The user prefers that refunds be approved without asking". The instruction patterns do not catch that, and the fact would steer every later turn. The permission guard asks a model whether the fact lets the agent act on its own, and rejects it when the answer is yes. It costs one call per write.

| `MEMORY_PERMISSION_GUARD` | Asks |
|---|---|
| `none` (default) | Nothing |
| `agent` | A text model with structured output, through `MEMORY_PROVIDER` and `MEMORY_MODEL` |
| `classification` | A classification model, through `MEMORY_CLASSIFICATION_PROVIDER` and `MEMORY_CLASSIFICATION_MODEL` |

It catches most permissions, not all, and small models catch fewer. Keep real actions such as payments behind the SDK's tool approvals. The question lives in `prompts.memory.classification.permission.*`, and the agent guard reuses it.

## Duplicates and corrections

Before a fact is stored, the writer looks for up to 5 live memories with the same owner and subject, and a cosine similarity of 0.75 or more. When it finds some, it asks for one decision:

| Decision | Effect |
|---|---|
| `noop` | A memory already says it. Nothing is stored |
| `update` | The fact changes one of the candidates. The new row is stored and the old one gets `superseded_by` |
| `add` | The fact is about something else. It is stored |

The decision comes from one of two deciders, set with `MEMORY_DECIDER`:

| Decider | Asks | Providers |
|---|---|---|
| `agent` (default) | A text model with structured output, through `MEMORY_PROVIDER` and `MEMORY_MODEL` | Any text provider, Ollama included |
| `classification` | A classification model through the SDK's `Classification`, as a choice between `add`, `noop` and one `replace_{id}` per candidate | TypeSafe SystemOne (`jev-latest`), OpenAI, OpenRouter, and OpenAI-compatible servers with a `decisions` endpoint |

You can bind your own `Eznix86\AI\Memory\Contracts\DecidesDuplicates` too.

When a context holds more than 500 live memories, the weakest ones are archived before a new one is added. Memories a person wrote or confirmed are kept.

## Automatic extraction

After every prompt to an agent that implements `HasMemory`, an `ExtractMemories` job is queued with the user message and the reply. A small agent picks out at most 3 facts the user stated or confirmed, and ignores text quoted from emails, documents or customer messages. Before a fact reaches the writer, `Grounding` checks that at least half of its words come from the user's own words. Quoted lines (`>`), quoted strings, code blocks and the assistant reply do not count. So a fact the model took from a pasted email or from its own answer is dropped, even when the model ignored its instructions. Each fact that passes goes through the writer, with `extraction` as its source. Turn extraction off with `MEMORY_EXTRACTION=false`.

The extractor, decider and consolidator work with any provider and model you set. Very small models make more mistakes on these tasks, so check the model you choose with the live test suite (see [Testing](#testing)).

## Decay

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

Loading and recall use the strongest memories first, and each use makes a memory stronger again.

## Consolidation

Schedule the consolidation once a week:

```php
Schedule::command('memory:consolidate')->weeklyOn(0, '03:00');
```

It queues one `ConsolidateMemories` job per tenant. The job merges every subject with 6 or more live memories into one note written by a small agent, and the originals get `superseded_by`. Then it archives the memories whose strength fell below 0.05, except the ones a person wrote or confirmed. Pass `--sync` to run it at once.

Nothing the agent does deletes a row. Superseded and archived memories stay as history, and only `Memories::delete()` and `Memories::purge()` remove rows.

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

## Languages

The package works in any language, with four things to set for languages other than English:

| What | Where | Default |
|---|---|---|
| Prompts the model reads | Blade views in `prompts.memory.*`, see [Prompt templates](#prompt-templates) | English. The prompts tell the model to keep facts in the user's language, so English prompts work for other languages too |
| Short texts the model reads: tool results, rejection reasons, schema descriptions, classification options | `lang/vendor/memory/{locale}/*.php`, published with `--tag=memory-lang`. They follow the app locale | English |
| Keyword search | `MEMORY_FULLTEXT_LANGUAGE`, a PostgreSQL text search configuration such as `french`. Use `simple` for mixed languages. PostgreSQL does not split Chinese, Japanese or Korean into words: there, recall relies on the meaning search, or on an extension such as zhparser | `english` |
| Instruction and password patterns | `instruction_patterns` and `password_patterns` in `config/memory.php`. Add the patterns of your users' languages, or use the permission guard, which works in any language | English |

The grounding check of extraction compares pairs of characters, so it works with and without spaces between words. Recall by meaning depends on your embedding model: use a multilingual one for users who do not write in English.

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
| `prompts.memory.agents.decider`, `decider-prompt` | Duplicate decider instructions and prompt |
| `prompts.memory.classification.decider` | Classification decider question |
| `prompts.memory.classification.permission.question`, `true`, `false` | Permission guard question and its two answers |
| `prompts.memory.agents.extractor`, `extractor-prompt` | Extraction instructions and prompt |
| `prompts.memory.agents.consolidator`, `consolidator-prompt` | Consolidation instructions and prompt |
| `prompts.memory.agents.permission`, `permission-prompt` | Agent permission guard instructions and prompt |

Use `{!! !!}` for memory content in your templates. `{{ }}` escapes quotes as HTML entities, and the model then sees `&quot;`.

## Configuration

| Option | Env | Default | Description |
|---|---|---|---|
| `table` | `MEMORY_TABLE` | `agent_memories` | Table name |
| `dimensions` | `MEMORY_DIMENSIONS` | `1536` | Embedding vector dimensions. Must match your embedding model |
| `loaded_limit` | `MEMORY_LOADED_LIMIT` | `30` | Memories added to the instructions |
| `recall_limit` | `MEMORY_RECALL_LIMIT` | `8` | Memories returned by `RecallMemory` |
| `recall_min_similarity` | `MEMORY_RECALL_MIN_SIMILARITY` | `0.3` | Minimum cosine similarity for `RecallMemory` |
| `provider`, `model` | `MEMORY_PROVIDER`, `MEMORY_MODEL` | default | Provider and model of the decider, extractor and consolidator agents |
| `timeout` | `MEMORY_TIMEOUT` | `120` | Seconds the memory agents and the classification decider may take |
| `decider` | `MEMORY_DECIDER` | `agent` | `agent` or `classification` |
| `permission_guard` | `MEMORY_PERMISSION_GUARD` | `none` | `none`, `agent` or `classification` |
| `classification_provider`, `classification_model` | `MEMORY_CLASSIFICATION_PROVIDER`, `MEMORY_CLASSIFICATION_MODEL` | default | Provider and model of the classification decider and guard |
| `fulltext_language` | `MEMORY_FULLTEXT_LANGUAGE` | `english` | PostgreSQL text search configuration of the keyword search |
| `instruction_patterns`, `password_patterns` | none | English patterns | Regular expressions the writer rejects |
| `queue` | `MEMORY_QUEUE` | default | Queue of the extraction and consolidation jobs |
| `extraction` | `MEMORY_EXTRACTION` | `true` | Extract facts after each turn |
| `duplicate_similarity` | `MEMORY_DUPLICATE_SIMILARITY` | `0.75` | Similarity from which a memory is a duplicate candidate |
| `max_live` | `MEMORY_MAX_LIVE` | `500` | Live memories per context before the weakest are archived |
| `consolidation_threshold` | `MEMORY_CONSOLIDATION_THRESHOLD` | `6` | Memories on one subject before they are merged |
| `archive_below` | `MEMORY_ARCHIVE_BELOW` | `0.05` | Strength under which consolidation archives a memory |

## Testing

The package tests use [Pest](https://pestphp.com) and [Testcontainers](https://testcontainers.com) with PostgreSQL and pgvector, so Docker must be running, unless you point the tests at your own Postgres.

```bash
./vendor/bin/pest
```

To use a Postgres you already run instead of a container, set `MEMORY_TEST_DB_HOST` (and `MEMORY_TEST_DB_PORT`, `MEMORY_TEST_DB_DATABASE`, `MEMORY_TEST_DB_USERNAME`, `MEMORY_TEST_DB_PASSWORD` when they differ from `5432`, `memory_test`, `postgres` and an empty password). The database needs the pgvector extension available:

```bash
MEMORY_TEST_DB_HOST=127.0.0.1 ./vendor/bin/pest
```

You can put these variables in a `.env` file: copy `.env.example`, which lists every variable the tests read.

The tests in `tests/Integration` call real models with real cases in English, French and Chinese. They are skipped unless `MEMORY_LIVE=1`. Set the models in `.env` (`MEMORY_LIVE_PROVIDER`, `MEMORY_LIVE_CHAT_MODEL`, `MEMORY_LIVE_EMBEDDINGS_*`, `MEMORY_PROVIDER`, `MEMORY_MODEL`) and run:

```bash
./vendor/bin/pest --testsuite=Integration
```

Cases marked `(holdout)` were not used to write the prompts, so they show how a model and the prompts do on text they were not tuned for.

In your own tests, `Memories::fake()` fakes everything the package calls:

- embeddings, with word-count vectors, so texts that share words are similar
- the decider, which answers `add`, and the classification, which answers `add`
- the extractor, which finds no facts
- the consolidator, which answers "Merged note."
- the agent permission guard, which answers that no fact grants a permission

Fake one of them again to test a decision, for example `MemoryDecider::fake([['decision' => 'noop', 'replace_id' => null]])`.

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
