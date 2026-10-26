# Safety, duplicates and decay

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

Before a fact is stored, the writer looks for up to 5 active memories with the same owner and subject, and a cosine similarity of 0.70 or more. If it finds some, it asks a decider:

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

When a context holds 500 active memories, the weakest ones are archived before a new one is added. A context counts the shared memories of its owner and the personal memories of its user, so shared memories count for every user. Memories a person wrote or confirmed are never archived this way.

## Decay and consolidation

Every memory has a strength from 0 to 1:

```
strength = min(1, importance × 0.5^(days unused / half-life) × (1 + 0.1 × min(recall count, 10)))
```

The half-life of each kind is in [the kinds table](memory-context.md#the-memory-context).

Each time a memory is loaded or recalled, it gets stronger again.

`memory:consolidate` queues one job per owner. The job merges each subject that has 6 or more active memories into one note written by a model, and the originals get `superseded_by`. Then it archives the memories whose strength fell below 0.05, except the ones a person wrote or confirmed. If the merged note is rejected, the originals stay and a warning is logged. Pass `--sync` to run it at once.

The command also reports memories whose owner no longer exists. Pass `--prune` to delete them. Better, delete them with their owner, for example in your `Team` model:

```php
protected static function booted(): void
{
    static::deleting(fn (Team $team) => AgentMemory::forOwner($team)->delete());
}
```
