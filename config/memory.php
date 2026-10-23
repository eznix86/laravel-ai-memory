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

];
