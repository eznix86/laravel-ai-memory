# Languages

## What works in any language

- **Facts stay in the user's language.** The prompts are in English, and they tell the model to write each fact in the language of the user's message, never translated. So a French user gets French memories, without any change.
- **Extraction checks work without spaces.** The check that a fact comes from the user's own words compares Chinese and Japanese text character by character, and other text by pairs of letters.

## What you set for your languages

| What | Where | Default | Why |
|---|---|---|---|
| Keyword search | `AI_MEMORY_FULLTEXT_LANGUAGE` | `english` | A PostgreSQL text search configuration, such as `french`. Use `simple` when your users write in several languages. Set it **before** `php artisan migrate`: the full-text index is built with it. PostgreSQL does not split Chinese, Japanese or Korean into words, so there recall relies on the meaning search, or on an extension such as zhparser |
| Meaning search | `AI_MEMORY_EMBEDDINGS_PROVIDER`, `AI_MEMORY_EMBEDDINGS_MODEL` | `config/ai.php` | Pick a multilingual embedding model when your users do not write in English |
| Rejected instructions and passwords | `instruction_patterns`, `password_patterns` in `config/memory.php` | English | Regular expressions. Add your languages, or use the [permission guard](safety.md#permission-guard), which works in any language |
| Messages | lang files | English | See below |

To reject French instructions too, publish the config and add a pattern:

```php
'instruction_patterns' => [
    // the English defaults...
    '/^\s*(toujours|jamais|ignore[rz]?)\b/iu',
],
```

## Translating the messages

The short texts come from lang files and follow the app locale: tool results such as "Remembered as #12.", rejection reasons, schema descriptions and classification answers. The agent reads them, so translate them when your agent works in another language.

```bash
php artisan vendor:publish --tag=memory-lang
```

This copies the English files to `lang/vendor/memory/en`. Copy that folder to `lang/vendor/memory/fr` and translate the lines. Keep the `:id` and `:old` placeholders:

```php
// lang/vendor/memory/fr/tools.php
return [
    'remembered' => 'Mémorisé sous #:id.',
    'replaced' => 'Mémorisé sous #:id. Remplace #:old.',
    // ...
];
```

Set the locale for each request, as for the rest of your app, for example with `App::setLocale($user->locale)` in a middleware. The extraction and consolidation jobs run on the queue worker, in its default locale.

## Prompts in another language

Keep the English prompts unless you have a reason: they already keep facts in the user's language, and models follow English instructions well. To change a prompt, override its view, see [Customizing prompts and messages](customizing.md#customizing-prompts-and-messages). A view has no locale of its own. A check such as `app()->getLocale()` inside an extraction or consolidation prompt sees the worker's locale, not the user's.
