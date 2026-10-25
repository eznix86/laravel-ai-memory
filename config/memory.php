<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Memory Table
    |--------------------------------------------------------------------------
    |
    | This option controls the name of the database table that holds the
    | agent memories. Change it before you run the migration if the name
    | clashes with a table that already exists in your application.
    |
    */

    'table' => env('AI_MEMORY_TABLE', 'agent_memories'),

    /*
    |--------------------------------------------------------------------------
    | Embeddings
    |--------------------------------------------------------------------------
    |
    | Here you may specify the provider and model that embed the memories,
    | if they differ from the embeddings defaults of config/ai.php, such as
    | a multilingual model for memory only. The dimensions are the size of
    | the vectors. Leave them empty to use the size the provider reports,
    | which the SDK also uses to embed. The migration reads them, so a
    | later change needs a migration. An Ollama provider reports no size:
    | set its embeddings dimensions in config/ai.php, or here.
    |
    */

    'embeddings_provider' => env('AI_MEMORY_EMBEDDINGS_PROVIDER') ?: null,

    'embeddings_model' => env('AI_MEMORY_EMBEDDINGS_MODEL') ?: null,

    'dimensions' => env('AI_MEMORY_DIMENSIONS') ? (int) env('AI_MEMORY_DIMENSIONS') : null,

    /*
    |--------------------------------------------------------------------------
    | Full-Text Language
    |--------------------------------------------------------------------------
    |
    | This option controls the PostgreSQL text search configuration used by
    | the keyword half of the RecallMemory search. It is read when the
    | migration builds the index, so a later change needs a new index. Use
    | "simple" when your users write in several languages. PostgreSQL does
    | not split Chinese, Japanese or Korean into words: there, recall relies
    | on the meaning half of the search, or on an extension such as zhparser.
    |
    | Supported: any configuration listed by "\dF" in psql, such as
    |            "english", "french", "german", "spanish", "simple"
    |
    */

    'fulltext_language' => env('AI_MEMORY_FULLTEXT_LANGUAGE', 'english'),

    /*
    |--------------------------------------------------------------------------
    | Loaded Memories
    |--------------------------------------------------------------------------
    |
    | Here you may specify how many memories the WithMemory middleware adds
    | to the agent instructions on every prompt. It loads the user's
    | preferences first, then the memories most relevant to the last user
    | messages, up to the relevant limit, then the strongest ones until the
    | loaded limit. The agent finds the others with the RecallMemory tool.
    | Every loaded memory costs tokens on every prompt. Set the loaded
    | limit to 0 to load nothing.
    |
    */

    'loaded_limit' => (int) env('AI_MEMORY_LOADED_LIMIT', 30),

    'relevant_limit' => (int) env('AI_MEMORY_RELEVANT_LIMIT', 8),

    /*
    |--------------------------------------------------------------------------
    | Recall Results
    |--------------------------------------------------------------------------
    |
    | These options control the RecallMemory tool. The limit is the number of
    | memories one search returns. The minimum similarity, from 0.0 to 1.0,
    | is the cosine similarity a memory needs to be found by meaning. Lower
    | values find more, and some of it may not be relevant. Keyword matches
    | are found whatever the minimum similarity is.
    |
    */

    'recall_limit' => (int) env('AI_MEMORY_RECALL_LIMIT', 8),

    'recall_min_similarity' => (float) env('AI_MEMORY_RECALL_MIN_SIMILARITY', 0.3),

    /*
    |--------------------------------------------------------------------------
    | Memory Agents
    |--------------------------------------------------------------------------
    |
    | Here you may specify the provider and model of the agents that decide
    | on duplicates, extract facts after each turn, merge memories and guard
    | against permissions. Any provider and model of config/ai.php with
    | structured output works. Leave them empty to use the defaults of
    | config/ai.php. The timeout is the number of seconds one answer may
    | take, for these agents and for the classification calls below.
    |
    */

    'provider' => env('AI_MEMORY_PROVIDER') ?: null,

    'model' => env('AI_MEMORY_MODEL') ?: null,

    'timeout' => (int) env('AI_MEMORY_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Duplicate Decider
    |--------------------------------------------------------------------------
    |
    | This option controls how the writer decides whether a new fact is
    | added, replaces a close memory, or is already known. The "agent"
    | decider asks the memory agents model above. The "classification"
    | decider asks a classification model, through the provider and model
    | below. Leave those empty to use "default_for_classification" of
    | config/ai.php and that provider's default classification model.
    |
    | Supported: "agent", "classification"
    |
    | Classification providers: "typesafe", "openai", "openrouter", and
    |                           "openai-compatible" with a decisions endpoint
    |
    */

    'decider' => env('AI_MEMORY_DECIDER', 'agent'),

    'classification_provider' => env('AI_MEMORY_CLASSIFICATION_PROVIDER') ?: null,

    'classification_model' => env('AI_MEMORY_CLASSIFICATION_MODEL') ?: null,

    /*
    |--------------------------------------------------------------------------
    | Instruction And Password Patterns
    |--------------------------------------------------------------------------
    |
    | The writer rejects a fact that matches one of these regular expressions.
    | They depend on the language, and the defaults only know English, so
    | add the patterns of the languages your users write in. An empty list
    | turns the check off. Card numbers, IBANs and API keys are checked in
    | every language. A capable model rephrases an instruction into a fact,
    | so these patterns are a first line only: the permission guard below
    | catches more.
    |
    */

    'instruction_patterns' => [
        '/^\s*(always|never|ignore|disregard|forget|do not|don\'t|you must|you should|you are to|from now on)\b/i',
        '/\b(ignore|disregard|override)\s+(all\s+|any\s+)?(previous|prior|above|earlier|your)\s+(instructions|rules|guidelines)\b/i',
        '/\b(system prompt|new instructions)\b/i',
    ],

    'password_patterns' => [
        '/\b(password|passwd|pwd|passcode|pin code)\b\s*(is|:|=)\s*\S+/i',
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Guard
    |--------------------------------------------------------------------------
    |
    | A fact such as "the user lets the agent approve refunds without asking"
    | would steer every later turn. This option controls whether the writer
    | asks a model if a fact lets the agent act on its own, and rejects it
    | when it does. The "agent" guard asks the memory agents model, and the
    | "classification" guard asks the classification model. Each write then
    | costs one more call. The guard catches most permissions, not all, so
    | keep real actions such as payments behind tool approvals.
    |
    | Supported: "none", "agent", "classification"
    |
    */

    'permission_guard' => env('AI_MEMORY_PERMISSION_GUARD', 'none'),

    /*
    |--------------------------------------------------------------------------
    | Automatic Extraction
    |--------------------------------------------------------------------------
    |
    | When extraction is enabled, every prompt to an agent that implements
    | HasMemory queues a job that reads the user message and the reply, and
    | stores up to three facts the user stated. Each job costs one call to
    | the memory agents model. The queue is the queue of the extraction and
    | consolidation jobs, on your default connection. Leave it empty to use
    | the default queue.
    |
    */

    'extraction' => (bool) env('AI_MEMORY_EXTRACTION', true),

    'queue' => env('AI_MEMORY_QUEUE') ?: null,

    /*
    |--------------------------------------------------------------------------
    | Duplicates And Capacity
    |--------------------------------------------------------------------------
    |
    | A stored memory with a cosine similarity of at least the duplicate
    | similarity may say the same thing as a new fact, and the writer then
    | asks the duplicate decider. Below it, the fact is added without asking.
    | When a context holds more live memories than the maximum, the weakest
    | ones are archived before a new one is added. Memories written or
    | confirmed by a person are never archived this way.
    |
    */

    'duplicate_similarity' => (float) env('AI_MEMORY_DUPLICATE_SIMILARITY', 0.75),

    'max_live' => (int) env('AI_MEMORY_MAX_LIVE', 500),

    /*
    |--------------------------------------------------------------------------
    | Consolidation
    |--------------------------------------------------------------------------
    |
    | The memory:consolidate command merges the live memories of one subject
    | and owner into one note when there are at least as many as the
    | threshold. It then archives the memories whose decayed strength falls
    | below the archive strength, from 0.0 (never) to 1.0. Memories written
    | or confirmed by a person are kept. With importance 0.5, a memory falls
    | below 0.05 after about 3.3 half-lives without use: 70 days for a
    | "context" memory, 600 days for a "preference".
    |
    */

    'consolidation_threshold' => (int) env('AI_MEMORY_CONSOLIDATION_THRESHOLD', 6),

    'archive_below' => (float) env('AI_MEMORY_ARCHIVE_BELOW', 0.05),

];
