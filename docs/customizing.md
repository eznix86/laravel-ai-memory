# Customizing

## Models

Nothing in the package depends on a specific model. Set the provider and model of the memory agents (decider, extractor, consolidator, agent permission guard) with `AI_MEMORY_PROVIDER` and `AI_MEMORY_MODEL`, or leave them empty to use the defaults of `config/ai.php`. The text model needs structured output.

Memories are embedded with the embeddings default of `config/ai.php`. To embed them with another provider or model, for example a multilingual one, set `AI_MEMORY_EMBEDDINGS_PROVIDER` and `AI_MEMORY_EMBEDDINGS_MODEL`.

These are small tasks, but very small models make more mistakes on them. Check the model you choose with the live test suite (see [Contributing](../CONTRIBUTING.md#contributing)). Raise `AI_MEMORY_TIMEOUT` for slow local models.

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
| `AI_MEMORY_PROVIDER_OPTIONS` | `{}` | JSON options per provider, sent with every request of the memory agents, such as `{"openrouter": {"temperature": 0}}` |
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
| `AI_MEMORY_MAX_ACTIVE_PER_CONTEXT` | `500` | Active memories per context before the weakest are archived |
