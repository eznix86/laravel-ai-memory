# Contributing

The tests need PostgreSQL with pgvector. Copy `.env.example` to `.env`. This file shows all the variables that the tests read.

```bash
cp .env.example .env
./vendor/bin/pest
```

If `DB_HOST` is set, the tests use that database. If not, they start a pgvector container with [Testcontainers](https://testcontainers.com). Docker must then run.

This command runs the unit and feature tests. They call no model.

The tests in `tests/Evals` are [Pest evals](https://pestphp.com/docs/evals). They call real models, with cases in English, French, Chinese and Japanese. Some cases are real conversations from [OpenAssistant OASST2](https://huggingface.co/datasets/OpenAssistant/oasst2) (Apache-2.0).

- The evals run only with `--evals`.
- They use the same `AI_MEMORY_*` settings as an app, and the provider keys of `config/ai.php`.
- A classification model checks if the stored memories contain the expected fact. By default this is TypeSafe, so also set `TYPESAFE_API_KEY`.

```bash
./vendor/bin/pest --evals
```

Cases marked `(holdout)` were not used to write the prompts. They show the results of a model and the prompts on new text.

Before you open a pull request, run the checks:

```bash
./vendor/bin/pint
./vendor/bin/rector
./vendor/bin/phpstan analyse
```
