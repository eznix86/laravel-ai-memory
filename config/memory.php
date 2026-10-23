<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Table Name
    |--------------------------------------------------------------------------
    |
    | The database table that holds the agent memories. Change it before you
    | run the migration if the default name clashes with your application.
    |
    | Default: "agent_memories"
    | Possible values: any table name
    | Example: MEMORY_TABLE=assistant_memories
    |
    */

    'table' => env('MEMORY_TABLE', 'agent_memories'),

    /*
    |--------------------------------------------------------------------------
    | Vector Dimensions
    |--------------------------------------------------------------------------
    |
    | The number of dimensions for the embedding vectors. This must match
    | the output dimensions of your embedding model. It is read when the
    | migration runs, so changing it later needs a new migration.
    |
    | Default: 1536
    | Possible values:
    | - 1536 (OpenAI text-embedding-3-small, text-embedding-ada-002)
    | - 3072 (OpenAI text-embedding-3-large, Gemini)
    | - 1024 (Cohere embed-english-v3.0)
    | - 768 (Ollama nomic-embed-text)
    | Example: MEMORY_DIMENSIONS=768
    |
    */

    'dimensions' => (int) env('MEMORY_DIMENSIONS', 1536),

    /*
    |--------------------------------------------------------------------------
    | Loaded Memory Limit
    |--------------------------------------------------------------------------
    |
    | The number of memories the WithMemory middleware adds to the agent
    | instructions on every prompt, strongest first. The agent reaches the
    | other memories with the RecallMemory tool. Each memory costs tokens on
    | every prompt, so keep it low.
    |
    | Default: 30
    | Possible values: any integer from 0, where 0 loads nothing
    | Example: MEMORY_LOADED_LIMIT=15
    |
    */

    'loaded_limit' => (int) env('MEMORY_LOADED_LIMIT', 30),

    /*
    |--------------------------------------------------------------------------
    | Recall Limit
    |--------------------------------------------------------------------------
    |
    | The maximum number of memories the RecallMemory tool returns for one
    | search.
    |
    | Default: 8
    | Possible values: any integer from 1
    | Example: MEMORY_RECALL_LIMIT=5
    |
    */

    'recall_limit' => (int) env('MEMORY_RECALL_LIMIT', 8),

    /*
    |--------------------------------------------------------------------------
    | Recall Minimum Similarity
    |--------------------------------------------------------------------------
    |
    | The minimum cosine similarity a memory needs to be found by meaning in
    | the RecallMemory tool. Lower values return more results, but some of
    | them may not be relevant. Keyword matches are found whatever this is.
    |
    | Default: 0.3
    | Possible values: a number from 0.0 (everything) to 1.0 (identical only)
    | Example: MEMORY_RECALL_MIN_SIMILARITY=0.4
    |
    */

    'recall_min_similarity' => (float) env('MEMORY_RECALL_MIN_SIMILARITY', 0.3),

    /*
    |--------------------------------------------------------------------------
    | Memory Agents Provider And Model
    |--------------------------------------------------------------------------
    |
    | The provider and model of the small agents that decide on duplicates,
    | extract facts after each turn and merge memories. They need structured
    | output. Very small models do these tasks badly: test the model you pick.
    |
    | Default: null, which uses the default provider and model of config/ai.php
    | Possible values: a provider name from config/ai.php, such as "openai",
    | "anthropic", "gemini" or "ollama", and a model of that provider
    | Example: MEMORY_PROVIDER=ollama
    |          MEMORY_MODEL=granite4:3b
    |
    */

    'provider' => env('MEMORY_PROVIDER'),

    'model' => env('MEMORY_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | Memory Agents Timeout
    |--------------------------------------------------------------------------
    |
    | The number of seconds the memory agents and the classification decider
    | may take for one answer. Local thinking models can need more than a
    | minute.
    |
    | Default: 120
    | Possible values: any integer from 1
    | Example: MEMORY_TIMEOUT=300
    |
    */

    'timeout' => (int) env('MEMORY_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Duplicate Decider
    |--------------------------------------------------------------------------
    |
    | How the writer decides whether a new fact is added, replaces a close
    | memory, or is already known.
    |
    | Default: "agent"
    | Possible values:
    | - "agent": a text model with structured output, through the memory
    |   agents provider and model above. Works with every text provider.
    | - "classification": a classification model, through the classification
    |   provider and model below. Works with "typesafe", "openai", "openrouter"
    |   and OpenAI-compatible providers that have a decisions endpoint.
    | Example: MEMORY_DECIDER=classification
    |
    | The classification provider and model default to null, which uses
    | "default_for_classification" of config/ai.php and that provider's
    | default classification model.
    | Example: MEMORY_CLASSIFICATION_PROVIDER=typesafe
    |          MEMORY_CLASSIFICATION_MODEL=jev-latest
    |
    */

    'decider' => env('MEMORY_DECIDER', 'agent'),

    'classification_provider' => env('MEMORY_CLASSIFICATION_PROVIDER'),

    'classification_model' => env('MEMORY_CLASSIFICATION_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | The queue the extraction and consolidation jobs are pushed to, on your
    | default queue connection.
    |
    | Default: null, which uses the default queue of the connection
    | Possible values: any queue name your workers listen to
    | Example: MEMORY_QUEUE=agents
    |
    */

    'queue' => env('MEMORY_QUEUE'),

    /*
    |--------------------------------------------------------------------------
    | Automatic Extraction
    |--------------------------------------------------------------------------
    |
    | When enabled, every prompt to an agent that implements HasMemory queues
    | a job that reads the user message and the reply, and stores up to three
    | facts the user stated or confirmed. Each run costs one call to the
    | memory agents model.
    |
    | Default: true
    | Possible values: true or false
    | Example: MEMORY_EXTRACTION=false
    |
    */

    'extraction' => (bool) env('MEMORY_EXTRACTION', true),

    /*
    |--------------------------------------------------------------------------
    | Duplicate Similarity
    |--------------------------------------------------------------------------
    |
    | The cosine similarity from which a stored memory may say the same thing
    | as a new fact. The writer then asks the duplicate decider to add the
    | fact, replace the old memory, or do nothing. Below it, the fact is
    | added without asking.
    |
    | Default: 0.75
    | Possible values: a number from 0.0 (ask for every fact) to 1.0 (ask only
    | for identical facts)
    | Example: MEMORY_DUPLICATE_SIMILARITY=0.8
    |
    */

    'duplicate_similarity' => (float) env('MEMORY_DUPLICATE_SIMILARITY', 0.75),

    /*
    |--------------------------------------------------------------------------
    | Maximum Live Memories
    |--------------------------------------------------------------------------
    |
    | The number of live memories a context can hold. Before a new memory is
    | added over this number, the weakest ones are archived. Memories written
    | or confirmed by a person are never archived this way.
    |
    | Default: 500
    | Possible values: any integer from 1
    | Example: MEMORY_MAX_LIVE=200
    |
    */

    'max_live' => (int) env('MEMORY_MAX_LIVE', 500),

    /*
    |--------------------------------------------------------------------------
    | Consolidation Threshold
    |--------------------------------------------------------------------------
    |
    | The number of live memories on one subject, with the same owner, from
    | which the weekly consolidation merges them into one note.
    |
    | Default: 6
    | Possible values: any integer from 2
    | Example: MEMORY_CONSOLIDATION_THRESHOLD=10
    |
    */

    'consolidation_threshold' => (int) env('MEMORY_CONSOLIDATION_THRESHOLD', 6),

    /*
    |--------------------------------------------------------------------------
    | Archive Strength
    |--------------------------------------------------------------------------
    |
    | The weekly consolidation archives memories whose decayed strength falls
    | below this value. Memories written or confirmed by a person are kept.
    | A memory with importance 0.5 falls below 0.05 after about 3.3
    | half-lives without use: 70 days for "context", 600 for "preference".
    |
    | Default: 0.05
    | Possible values: a number from 0.0 (never archive) to 1.0
    | Example: MEMORY_ARCHIVE_BELOW=0.02
    |
    */

    'archive_below' => (float) env('MEMORY_ARCHIVE_BELOW', 0.05),

];
