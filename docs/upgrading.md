# Upgrading from 1.x to 2.x

2.x replaces the 1.x API and table. Your 1.x memories stay in the old `memories` table until you import them.

**1. Update the packages.** 2.x needs the Laravel AI SDK 1.3 or later.

```bash
composer require eznix86/laravel-ai-memory:^2.0 laravel/ai:^1.3
```

**2. Replace a published `config/memory.php`.** In the 1.x file, `table` is `memories`. With this file, the 2.x migration tries to make its table with the same name. Delete the file. Publish it again if you need it.

The env names also changed:

| 1.x | 2.x |
|---|---|
| `MEMORY_TABLE` | `AI_MEMORY_TABLE`, a new table: `agent_memories` |
| `MEMORY_DIMENSIONS` | `AI_MEMORY_DIMENSIONS` |
| `MEMORY_RECALL_LIMIT` | `AI_MEMORY_RECALL_LIMIT` |
| `MEMORY_MIDDLEWARE_RECALL_LIMIT` | `AI_MEMORY_LOADED_LIMIT` |
| `MEMORY_SIMILARITY_THRESHOLD` | `AI_MEMORY_RECALL_MIN_SIMILARITY` |
| `MEMORY_RECALL_OVERSAMPLE_FACTOR` | removed |

**3. Migrate.** This makes the `agent_memories` table. The `memories` table stays.

```bash
php artisan migrate
```

**4. Update your agents.** See [Add it to your project](../README.md#add-it-to-your-project).

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

**5. Import the old memories.** Do a dry run first:

```bash
php artisan memory:import-v1 --user-model="App\Models\User" --dry-run
php artisan memory:import-v1 --user-model="App\Models\User"
```

- The command writes each old row for the user of its `user_id`, as a personal `profile` memory with its old date.
- Add `--kind` for a different kind. Add `--table` if your 1.x table has a different name.
- The writer checks each row as a new fact. It skips repeated memories. It rejects secrets, instructions and texts longer than 300 characters.
- The command shows a table of the results. If you run it again, it skips the rows that it already imported.

Each row costs one embedding call. When a close memory exists, it also costs one call to the duplicate decider.

**6. Drop the old table** after you check the result with `Memories::list()`.
