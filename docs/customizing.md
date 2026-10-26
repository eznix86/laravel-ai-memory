# Customizing

## Models

The package works with all models of the SDK. Four memory agents use a text model: the decider, the extractor, the consolidator and the agent permission guard.

- Set their provider and model with `AI_MEMORY_PROVIDER` and `AI_MEMORY_MODEL`. If you do not set them, they use the defaults of `config/ai.php`.
- The text model must support structured output.
- The memories use the embeddings default of `config/ai.php`. For another provider or model, for example a multilingual one, set `AI_MEMORY_EMBEDDINGS_PROVIDER` and `AI_MEMORY_EMBEDDINGS_MODEL`.

The tasks are small, but very small models make more mistakes. Test your model with the evals (see [Contributing](../CONTRIBUTING.md#contributing)). For slow local models, increase `AI_MEMORY_TIMEOUT`.

## Customizing prompts and messages

```bash
php artisan vendor:publish --tag=memory-config   # config/memory.php
php artisan vendor:publish --tag=memory-views    # resources/views/prompts/memory
php artisan vendor:publish --tag=memory-lang     # lang/vendor/memory
```

The prompts are Blade views. To change a prompt, put a file with the same name in `resources/views/prompts/memory` of your app.

Use `{!! !!}` for memory text. `{{ }}` escapes quotes, and then the model sees `&quot;`.

| View | Used for |
|---|---|
| `prompts.memory.instructions`, `memory` | The memories in the instructions, and one memory line |
| `prompts.memory.tools.*` | The descriptions and results of `Remember`, `Forget` and `RecallMemory` |
| `prompts.memory.skill.*` | The description and instructions of `MemorySkill` |
| `prompts.memory.agents.decider`, `decider-prompt` | The duplicate decider |
| `prompts.memory.agents.extractor`, `extractor-prompt` | Extraction |
| `prompts.memory.agents.consolidator`, `consolidator-prompt` | Consolidation |
| `prompts.memory.agents.permission`, `permission-prompt` | The agent permission guard |
| `prompts.memory.classification.decider` | The question of the classification decider |
| `prompts.memory.classification.permission.*` | The permission question and its two answers, for both guards |

## Extending the package

The package gets its agents, deciders, guards, services and embedder from the container. To replace one, bind your own class in a service provider:

```php
use Eznix86\AI\Memory\Agents\MemoryExtractor;

$this->app->bind(MemoryExtractor::class, TeamExtractor::class);
```

| Class | Role |
|---|---|
| `Agents\MemoryExtractor`, `MemoryDecider`, `MemoryConsolidator`, `PermissionChecker` | The memory agents |
| `Deciders\AgentDecider`, `ClassificationDecider`, or the `Contracts\DecidesDuplicates` contract | The duplicate decider |
| `PermissionGuards\*`, or the `Contracts\GuardsPermissions` contract | The permission guard |
| `Services\MemoryWriter`, `MemorySearch`, `MemoryManager`, `Grounding` | Writing, search, management, word check |
| `Embedder` | The vectors of memories and queries |

## Configuration

`config/memory.php` explains all settings. These are the settings that you will most often change:

| Env | Default | What it does |
|---|---|---|
| `AI_MEMORY_EMBEDDINGS_PROVIDER`, `AI_MEMORY_EMBEDDINGS_MODEL` | `config/ai.php` defaults | The provider and model that make the vectors |
| `AI_MEMORY_DIMENSIONS` | the size of the provider | The vector size. Set it only if the provider gives no size, for example Ollama |
| `AI_MEMORY_PROVIDER`, `AI_MEMORY_MODEL` | `config/ai.php` defaults | The model of the memory agents |
| `AI_MEMORY_PROVIDER_OPTIONS` | `{}` | JSON options for each provider, sent with each request of the memory agents, such as `{"openrouter": {"temperature": 0}}` |
| `AI_MEMORY_TIMEOUT` | `120` | The maximum seconds for one answer of a memory agent |
| `AI_MEMORY_DECIDER` | `agent` | `agent` or `classification` |
| `AI_MEMORY_PERMISSION_GUARD` | `none` | `none`, `agent` or `classification` |
| `AI_MEMORY_CLASSIFICATION_PROVIDER`, `AI_MEMORY_CLASSIFICATION_MODEL` | `config/ai.php` defaults | The model of the classification decider and guard |
| `AI_MEMORY_EXTRACTION` | `true` | Extract facts after each turn |
| `AI_MEMORY_EXTRACTION_WINDOW` | `4` | The number of messages before the turn that the extractor reads. `0` sends the turn only |
| `AI_MEMORY_QUEUE` | default queue | The queue of the extraction and consolidation jobs |
| `AI_MEMORY_LOADED_LIMIT` | `30` | The maximum memories in the instructions |
| `AI_MEMORY_RELEVANT_LIMIT` | `8` | Of these, the memories that match the last user messages |
| `AI_MEMORY_MAX_ACTIVE_PER_CONTEXT` | `500` | The maximum active memories in a context. Above it, the weakest are archived |
