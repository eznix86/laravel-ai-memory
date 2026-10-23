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
    */

    'table' => env('MEMORY_TABLE', 'agent_memories'),

    /*
    |--------------------------------------------------------------------------
    | Vector Dimensions
    |--------------------------------------------------------------------------
    |
    | The number of dimensions for the embedding vectors. This should match
    | the output dimensions of your configured embedding model. Common values:
    | - 1536 (OpenAI text-embedding-3-small, text-embedding-ada-002)
    | - 3072 (OpenAI text-embedding-3-large)
    | - 1024 (Cohere embed-english-v3.0)
    | - 768 (Ollama nomic-embed-text)
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
    | other memories with the RecallMemory tool.
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
    */

    'recall_limit' => (int) env('MEMORY_RECALL_LIMIT', 8),

    /*
    |--------------------------------------------------------------------------
    | Recall Minimum Similarity
    |--------------------------------------------------------------------------
    |
    | The minimum cosine similarity (0.0 to 1.0) a memory needs to be returned
    | by the RecallMemory tool. Lower values return more results, but some of
    | them may not be relevant.
    |
    */

    'recall_min_similarity' => (float) env('MEMORY_RECALL_MIN_SIMILARITY', 0.3),

    /*
    |--------------------------------------------------------------------------
    | Memory Agents Provider And Model
    |--------------------------------------------------------------------------
    |
    | The provider and model of the small agents that decide on duplicates,
    | extract facts after each turn and merge memories. A small, cheap model
    | is enough. Leave them empty to use your default provider and model.
    |
    */

    'provider' => env('MEMORY_PROVIDER'),

    'model' => env('MEMORY_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | Duplicate Decider
    |--------------------------------------------------------------------------
    |
    | How the writer decides whether a new fact is added, replaces a close
    | memory, or is already known. "agent" asks a text model with structured
    | output, through the provider and model above. "classification" asks a
    | classification model, such as TypeSafe SystemOne, through the provider
    | and model below. Leave those empty to use your classification default.
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
    | The queue the extraction and consolidation jobs are pushed to. Leave it
    | empty to use the default queue of your default connection.
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
    | facts the user stated or confirmed.
    |
    */

    'extraction' => (bool) env('MEMORY_EXTRACTION', true),

    /*
    |--------------------------------------------------------------------------
    | Duplicate Similarity
    |--------------------------------------------------------------------------
    |
    | The cosine similarity from which a stored memory may say the same thing
    | as a new fact. The writer then asks a small model to add the fact,
    | replace the old memory, or do nothing.
    |
    */

    'duplicate_similarity' => (float) env('MEMORY_DUPLICATE_SIMILARITY', 0.75),

    /*
    |--------------------------------------------------------------------------
    | Maximum Live Memories
    |--------------------------------------------------------------------------
    |
    | The number of live memories a context can hold. Before a new memory is
    | added over this number, the weakest ones are archived.
    |
    */

    'max_live' => (int) env('MEMORY_MAX_LIVE', 500),

    /*
    |--------------------------------------------------------------------------
    | Consolidation Threshold
    |--------------------------------------------------------------------------
    |
    | The number of live memories on one subject from which the weekly
    | consolidation merges them into one.
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
    |
    */

    'archive_below' => (float) env('MEMORY_ARCHIVE_BELOW', 0.05),

];
