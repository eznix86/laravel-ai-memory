# The memory context

The agent reads and writes all memories in a `MemoryContext`:

```php
use Eznix86\AI\Memory\MemoryContext;

$context = new MemoryContext(
    user: $request->user(),             // the user the agent talks to
    owner: $team,                       // optional: any Eloquent model whose members share the memories
    conversation: $conversationId,      // optional: an id or a model, recorded as the source of new memories
);
```

A context needs a user, an owner, or both.

**Without an owner**, the user is also the owner. All memories of that user are private, of all kinds. The kind then changes only how fast a memory fades, and which memories load first. Use this for a single-user app:

```php
new MemoryContext(user: $user);
```

**With an owner**, such as a team, the team shares most memories. The profile and preference memories of each user stay private.

**Use one rule in your app.** The memories of a context without an owner do not show in a team context, because the owner is different. If your app uses both, each user gets two separate sets of memories. The limit of active memories counts each context separately. The weekly clean-up works on each owner separately.

With the SDK's `RemembersConversations`, the agent can make the context from the conversation:

```php
public function memory(): MemoryContext
{
    return new MemoryContext(user: $this->conversationParticipant(), owner: $this->team, conversation: $this->currentConversation());
}
```

## Kinds

Each memory has a **kind**. The kind sets who sees the memory and how fast it fades.

| Kind | Use it for | Example | Seen by | Half-life |
|---|---|---|---|---|
| `profile` | Who the user is, and what they have or do | The user is a chemistry student and uses Arch Linux. | The user | 365 days |
| `preference` | How the user likes things done | The user prefers short answers. | The user | 180 days |
| `process` | How the team works | Refunds above 500 francs need a manager. | The owner | 365 days |
| `correction` | A fact that was wrong, and the correct fact | The Geneva office closed in 2025. | The owner | 365 days |
| `customer_note` | What a colleague tells you about one customer, and no system holds | Customer 42 prefers a phone call to reminder emails. | The owner | 120 days |
| `lesson` | What the team learned from a mistake or a success | The export fails when a name has an emoji. | The owner | 90 days |
| `context` | A temporary situation | The team is in a release freeze this week. | The owner | 21 days |

Do not keep data that a system already holds. An example is "Customer 42 has not paid the March invoice". The customer can pay tomorrow, but the memory stays. Give the agent a tool that reads your billing data. Use a `customer_note` for how to work with that customer. The extractor and the `Remember` tool skip invoices, records and statuses.

## Subjects

A **subject** is a stable id in the form `type:id`, such as `customer:42` or `project:apollo`. Consolidation and the privacy purge use the subject.

The writer rejects other subjects, and the tools remove them. A label that a model writes, such as "team deployment", changes from one fact to the next. Then two copies of a fact get different labels, and the copies stay apart.

## Tools, middleware and skill

| Part | What it does |
|---|---|
| `Remember` | Stores one fact: a kind, a text of 300 characters or less, an optional subject, and an importance from 0 to 1. It tells the agent if the fact is rejected, already known, or replaces a memory |
| `Forget` | Archives a memory by its id. A person can restore it |
| `RecallMemory` | Finds the memories that are not loaded. It ranks them by keyword (PostgreSQL full text, any word of the query) and by meaning (pgvector), then joins the two rankings. Strength decides only between equal matches. It finds archived memories only when the agent asks for them |
| `WithMemory` | Adds memories to the instructions once per prompt, and keeps them after tool calls. First it adds the user's preferences. Then it adds the 8 memories that match the last 3 user messages best. Then it adds the strongest memories, up to 30 in total. So it loads an old note about a customer that the user names, also when the note is weak |
| `MemorySkill` | Guidance that the agent loads with the SDK's `LoadSkill` tool: which facts to keep, which kind to use, and how to correct a fact |

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

The tool descriptions also contain the safety rules, because small models often do not load skills.
