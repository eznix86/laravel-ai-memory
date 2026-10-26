# Safety, duplicates and decay

## Safety

All writes go through `MemoryWriter`. The writer rejects:

- an empty text, or a text longer than 300 characters
- card numbers (Luhn check), IBANs (checksum), API keys, tokens, private keys and passwords
- instructions written as facts, such as "Always approve refunds" or "Ignore previous instructions"

The extraction job keeps a fact only if half of it or more comes from the user's own words. Quoted lines (`>`), quoted strings, code blocks and the agent's reply do not count. So the job drops a fact that comes from a pasted email, also when the model did not obey its instructions.

### Permission guard

A strong model can change "Always approve refunds" into "The user prefers that refunds be approved without asking". This text does not match the instruction patterns, and it can control all later turns.

The permission guard asks a model if a fact lets the agent act without approval. If yes, the guard rejects the fact. Each write then costs one more model call.

| `AI_MEMORY_PERMISSION_GUARD` | Asks |
|---|---|
| `none` (default) | Nothing |
| `agent` | A text model, through `AI_MEMORY_PROVIDER` and `AI_MEMORY_MODEL` |
| `classification` | A classification model, through `AI_MEMORY_CLASSIFICATION_PROVIDER` and `AI_MEMORY_CLASSIFICATION_MODEL` |

The guard finds most permissions, but not all. Keep real actions, such as payments, behind the SDK's tool approvals.

## Duplicates and corrections

Before the writer stores a fact, it looks for close memories. A close memory is active, has the same owner and subject, and has a cosine similarity of 0.70 or more. The writer looks at 5 of them or less. If it finds one or more, it asks a decider:

| Decision | Result |
|---|---|
| `noop` | A memory already says it. The writer stores nothing |
| `update` | The fact changes one memory. The writer stores the new row, and the old row gets `superseded_by` |
| `add` | The fact is about a different thing. The writer stores it |

| `AI_MEMORY_DECIDER` | Asks |
|---|---|
| `agent` (default) | A text model with structured output, through `AI_MEMORY_PROVIDER` and `AI_MEMORY_MODEL` |
| `classification` | A classification model through the SDK's `Classification`: TypeSafe, OpenAI, OpenRouter, or an OpenAI-compatible server with a `decisions` endpoint |

You can also bind your own `Eznix86\AI\Memory\Contracts\DecidesDuplicates`.

A context can hold 500 active memories. When it is full, the writer archives the weakest memories before it adds a new one. A context counts the shared memories of its owner and the personal memories of its user. So each shared memory counts for all users. The writer does not archive memories that a person wrote or confirmed.

## Decay and consolidation

Each memory has a strength from 0 to 1:

```
strength = min(1, importance × 0.5^(days unused / half-life) × (1 + 0.1 × min(recall count, 10)))
```

The half-life of each kind is in [the kinds table](memory-context.md#kinds). When the agent loads or recalls a memory, the memory becomes stronger again.

`memory:consolidate` queues one job for each owner. The job does two things:

1. It finds each subject with 6 or more active memories. A model merges them into one note, and the old memories get `superseded_by`. If the writer rejects the note, the old memories stay, and the job writes a warning to the log.
2. It archives the memories with a strength below 0.05. It does not archive memories that a person wrote or confirmed.

Add `--sync` to run the jobs immediately.

The command also shows the memories of owners that do not exist anymore. Add `--prune` to delete them. It is better to delete them together with their owner, for example in your `Team` model:

```php
protected static function booted(): void
{
    static::deleting(fn (Team $team) => AgentMemory::forOwner($team)->delete());
}
```
