# Upgrading from 1.x to 2.x

2.x replaces the 1.x API and table. Your 1.x memories stay in the old `memories` table until you import them.

**1. Update the packages.** 2.x needs the Laravel AI SDK 1.3 or later.

```bash
composer require eznix86/laravel-ai-memory:^2.0 laravel/ai:^1.3
```

**2. Replace a published `config/memory.php`.** The 1.x file sets `table` to `memories`, so the 2.x migration would try to create its table there. Delete the file, then publish it again if you need it. The env names changed too:

| 1.x | 2.x |
|---|---|
| `MEMORY_TABLE` | `AI_MEMORY_TABLE`, a new table: `agent_memories` |
| `MEMORY_DIMENSIONS` | `AI_MEMORY_DIMENSIONS` |
| `MEMORY_RECALL_LIMIT` | `AI_MEMORY_RECALL_LIMIT` |
| `MEMORY_MIDDLEWARE_RECALL_LIMIT` | `AI_MEMORY_LOADED_LIMIT` |
| `MEMORY_SIMILARITY_THRESHOLD` | `AI_MEMORY_RECALL_MIN_SIMILARITY` |
| `MEMORY_RECALL_OVERSAMPLE_FACTOR` | removed |

**3. Migrate.** This creates `agent_memories` and leaves `memories` in place.

```bash
php artisan migrate
```

**4. Update your agents**, as in [Add it to your project](../README.md#add-it-to-your-project):

| 1.x | 2.x |
|---|---|
| `['user_id' => $userId]` | `new MemoryContext(user: $user)` |
| `StoreMemory` tool | `Remember` |
| `(new RecallMemory)->context($context)` | `new RecallMemory($context)` |
| `new WithMemory($context, limit: 5)` | `new WithMemory($context)`, and the agent implements `HasMemory` |
| `AgentMemory::store()`, `all()`, `forget()`, `forgetAll()` | `Memories::add()`, `list()`, `delete()`, `purge()` |
| `AgentMemory::recall()` | the `RecallMemory` tool, or `Memories::list($context, search: '...')` |
| `AgentMemory::fake()` | `Memories::fake()` |

`AgentMemory` is now the Eloquent model in `Eznix86\AI\Memory\Models`.

**5. Import the old memories.** Start with a dry run to see what would happen:

```bash
php artisan memory:import-v1 --user-model="App\Models\User" --dry-run
php artisan memory:import-v1 --user-model="App\Models\User"
```

Each old row is written for the user its `user_id` points to, as a personal `profile` memory with its old date. Pass `--kind` for another kind, and `--table` if your 1.x table had another name. The rows go through the writer like any new fact, so a repeated memory is skipped, and secrets, instructions and memories longer than 300 characters are rejected. The command prints a table of what happened to the rows, and running it again skips the rows it already imported.

Each row costs one embedding call, and one call to the duplicate decider when a close memory exists.

**6. Drop the old table** when you have checked the result with `Memories::list()`.
