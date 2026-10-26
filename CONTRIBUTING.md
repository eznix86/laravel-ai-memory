# Contributing

The tests need PostgreSQL with pgvector. Copy `.env.example` to `.env`: it lists every variable the tests read.

```bash
cp .env.example .env
./vendor/bin/pest
```

With `DB_HOST` set, the tests use that database. Without it, they start a pgvector container with [Testcontainers](https://testcontainers.com), so Docker must be running.

This runs the unit and feature tests, which call no model. The tests in `tests/Evals` are [Pest evals](https://pestphp.com/docs/evals): they call real models with cases in English, French, Chinese and Japanese, including real conversations from [OpenAssistant OASST2](https://huggingface.co/datasets/OpenAssistant/oasst2) (Apache-2.0). They are skipped unless you pass `--evals`. They use the same `AI_MEMORY_*` settings as an application, and the provider keys of `config/ai.php`. A classification model judges whether the stored memories hold the expected fact, by default TypeSafe, so set `TYPESAFE_API_KEY` too:

```bash
./vendor/bin/pest --evals
```

Cases marked `(holdout)` were not used to write the prompts. They show how a model and the prompts do on text they were not tuned for.

Before a pull request, run the checks:

```bash
./vendor/bin/pint
./vendor/bin/rector
./vendor/bin/phpstan analyse
```
