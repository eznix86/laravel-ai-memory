# The memory context

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

Each memory has a **kind**. It sets who sees the memory and how fast it fades.

| Kind | Use it for | Example | Seen by | Half-life |
|---|---|---|---|---|
| `profile` | Who the user is and what they have or do | The user is a chemistry student and uses Arch Linux. | The user | 365 days |
| `preference` | How the user likes things done | The user prefers short answers. | The user | 180 days |
| `process` | How the team works | Refunds above 500 francs need a manager. | The owner | 365 days |
| `correction` | Something that was wrong and is now known | The Geneva office closed in 2025. | The owner | 365 days |
| `customer_note` | What a colleague would tell you about one customer, and no system holds | Customer 42 prefers a phone call to reminder emails. | The owner | 120 days |
| `lesson` | Something learned from a mistake or a success | The export fails when a name has an emoji. | The owner | 90 days |
| `context` | A temporary situation | The team is in a release freeze this week. | The owner | 21 days |

Do not store what a system already knows, such as "Customer 42 has not paid the March invoice". The customer pays tomorrow, and the memory is still there. Give the agent a tool that reads your billing data instead, and keep a `customer_note` for how to deal with that customer. The extractor and the `Remember` tool are told to skip invoices, records and statuses.

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
