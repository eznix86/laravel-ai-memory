# Languages

## What works in any language

- **Facts stay in the language of the user.** The prompts are in English. They tell the model to write each fact in the language of the user's message, without translation. So a French user gets French memories, with no change.
- **The word check works without spaces.** The extraction job checks that a fact uses the user's own words. It compares Chinese and Japanese text one character at a time, and other text in pairs of letters.

## What you set for your languages

| What | Where | Default | Why |
|---|---|---|---|
| Search | `AI_MEMORY_EMBEDDINGS_PROVIDER`, `AI_MEMORY_EMBEDDINGS_MODEL` | `config/ai.php` | Recall ranks memories by meaning. Use a multilingual embedding model if your users do not write in English |
| Rejected instructions and passwords | `instruction_patterns`, `password_patterns` in `config/memory.php` | English | Regular expressions. Add patterns for your languages, or use the [permission guard](safety.md#permission-guard), which works in all languages |
| Messages | lang files | English | See [Translating the messages](#translating-the-messages) |

To also reject French instructions, publish the config and add a pattern:

```php
'instruction_patterns' => [
    // the English defaults...
    '/^\s*(toujours|jamais|ignore[rz]?)\b/iu',
],
```

## Translating the messages

The short texts come from lang files, in the locale of the app. These texts are the tool results, such as "Remembered as #12.", the rejection reasons, the schema descriptions and the classification answers. The agent reads them. Translate them if your agent speaks another language.

1. Publish the lang files. This copies the English files to `lang/vendor/memory/en`:

    ```bash
    php artisan vendor:publish --tag=memory-lang
    ```

2. Copy that folder to `lang/vendor/memory/fr`.
3. Translate the lines. Do not change the `:id` and `:old` placeholders:

    ```php
    // lang/vendor/memory/fr/tools.php
    return [
        'remembered' => 'Mémorisé sous #:id.',
        'replaced' => 'Mémorisé sous #:id. Remplace #:old.',
        // ...
    ];
    ```

4. Set the locale for each request, as for the rest of your app. For example, call `App::setLocale($user->locale)` in a middleware.

The extraction and consolidation jobs run on the queue worker, in the default locale of the worker.

## Prompts in another language

Keep the English prompts if you can. They already keep facts in the language of the user, and models obey English instructions well.

To change a prompt, override its view. See [Customizing prompts and messages](customizing.md#customizing-prompts-and-messages). A view has no locale. In an extraction or consolidation prompt, `app()->getLocale()` gives the locale of the worker, not the locale of the user.
