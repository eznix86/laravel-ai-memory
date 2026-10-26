<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Services;

use Eznix86\AI\Memory\Contracts\DecidesDuplicates;
use Eznix86\AI\Memory\Contracts\GuardsPermissions;
use Eznix86\AI\Memory\Embedder;
use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\Enums\WriteOutcome;
use Eznix86\AI\Memory\Exceptions\RejectedMemory;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\MemoryWrite;
use Eznix86\AI\Memory\Models\AgentMemory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MemoryWriter
{
    public const int MAX_LENGTH = 300;

    public const string SUBJECT_PATTERN = '/^[a-z][a-z0-9_]*:[A-Za-z0-9_.-]+$/';

    /**
     * Patterns of API keys, tokens and private keys.
     *
     * @var list<string>
     */
    protected const array API_KEY_PATTERNS = [
        '/\b(sk|pk|rk)[-_][A-Za-z0-9_-]{16,}/',
        '/\bAKIA[0-9A-Z]{16}\b/',
        '/\bgh[pousr]_[A-Za-z0-9]{30,}/',
        '/\bxox[abprs]-[A-Za-z0-9-]{10,}/',
        '/\bAIza[0-9A-Za-z_-]{35}\b/',
        '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
        '/\b(api[_ -]?key|secret|access[_ -]?token|bearer)\b\s*(is|:|=)\s*\S{8,}/i',
    ];

    /**
     * Store a fact in the given context.
     *
     * @param  array{kind: MemoryKind|string, content: string, subject?: string|null, importance?: float|null, expires_at?: \DateTimeInterface|null, source: string, creator?: Model|null, personal?: bool|null}  $fact
     *
     * @throws RejectedMemory
     */
    public function write(MemoryContext $context, array $fact): MemoryWrite
    {
        $fact = $this->validate($fact);

        $kind = MemoryKind::from($fact['kind']);

        $personal = $fact['personal'] ?? $kind->isPersonal();

        if ($personal && ! $context->user instanceof Model) {
            throw RejectedMemory::invalid(__('memory::rejections.personal_without_user'));
        }

        $memory = new AgentMemory([
            'kind' => $kind,
            'subject' => $fact['subject'] ?? null,
            'content' => $fact['content'],
            'importance' => $fact['importance'] ?? 0.5,
            'expires_at' => $fact['expires_at'] ?? null,
            'source' => $fact['source'],
        ]);

        $memory->owner()->associate($context->owner);

        if ($personal) {
            $memory->user()->associate($context->user);
        }

        if (($fact['creator'] ?? null) instanceof Model) {
            $memory->creator()->associate($fact['creator']);
        }

        $memory->embedding = app(Embedder::class)->embed($memory->embeddingText());

        $candidates = $this->candidates($context, $memory);

        if ($candidates->isEmpty()) {
            return $this->add($context, $memory);
        }

        $decision = app(DecidesDuplicates::class)->decide($memory, $candidates);

        $chosen = $candidates->first(fn (AgentMemory $candidate): bool => $candidate->id === $decision['replace_id']);

        return match (true) {
            $decision['decision'] === 'noop' => new MemoryWrite(WriteOutcome::Known, $chosen ?? $candidates->firstOrFail()),
            $decision['decision'] === 'update' && $chosen instanceof AgentMemory => $this->replace($chosen, $memory),
            default => $this->add($context, $memory),
        };
    }

    /**
     * Find the live memories that may say the same thing as the new one.
     *
     * @return Collection<int, AgentMemory>
     */
    protected function candidates(MemoryContext $context, AgentMemory $memory): Collection
    {
        return AgentMemory::query()
            ->forContext($context)
            ->live()
            ->when($memory->user, fn (Builder $query, Model $user) => $query->whereBelongsToUser($user), fn (Builder $query) => $query->whereNull('user_id'))
            ->when($memory->subject, fn (Builder $query, string $subject) => $query->where('subject', $subject))
            ->whereVectorSimilarTo('embedding', $memory->embedding ?? [], config('memory.duplicate_similarity'))
            ->limit(5)
            ->get();
    }

    /**
     * Store a new memory, archiving the weakest ones when the context is full.
     */
    protected function add(MemoryContext $context, AgentMemory $memory): MemoryWrite
    {
        $live = AgentMemory::query()->forContext($context)->live();

        $excess = $live->count() - config('memory.max_live') + 1;

        if ($excess > 0) {
            AgentMemory::query()->forContext($context)->live()->whereNull('creator_id')->weakestFirst()->limit($excess)->get()
                ->each(fn (AgentMemory $weak) => $weak->update(['archived_at' => now()]));
        }

        $memory->save();

        return new MemoryWrite(WriteOutcome::Added, $memory);
    }

    /**
     * Store a new memory that supersedes an old one.
     */
    protected function replace(AgentMemory $old, AgentMemory $memory): MemoryWrite
    {
        DB::transaction(function () use ($old, $memory): void {
            $memory->save();
            $old->update(['superseded_by' => $memory->id]);
        });

        return new MemoryWrite(WriteOutcome::Updated, $memory, $old);
    }

    /**
     * Validate a fact before it is stored.
     *
     * @param  array<string, mixed>  $fact
     * @return array<string, mixed>
     *
     * @throws RejectedMemory
     */
    public function validate(array $fact): array
    {
        if (($fact['kind'] ?? null) instanceof MemoryKind) {
            $fact['kind'] = $fact['kind']->value;
        }

        $validator = Validator::make($fact, [
            'kind' => ['required', Rule::enum(MemoryKind::class)],
            'content' => ['required', 'string', 'max:'.self::MAX_LENGTH],
            'subject' => ['nullable', 'string', 'max:255', 'regex:'.self::SUBJECT_PATTERN],
            'importance' => ['nullable', 'numeric', 'between:0,1'],
            'source' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            throw RejectedMemory::invalid($validator->errors()->first());
        }

        $this->guardContent($fact['content']);

        if (app(GuardsPermissions::class)->grantsPermission($fact['content'])) {
            throw RejectedMemory::permission();
        }

        return $fact;
    }

    /**
     * Keep a subject only when it is a stable id such as "customer:42", so a model's free-form label never splits duplicates apart.
     */
    public static function subject(mixed $subject): ?string
    {
        return is_string($subject) && Str::isMatch(self::SUBJECT_PATTERN, $subject) ? $subject : null;
    }

    /**
     * Reject content that holds a secret or an instruction.
     *
     * @throws RejectedMemory
     */
    public function guardContent(string $content): void
    {
        if (Str::isMatch(config('memory.instruction_patterns'), $content)) {
            throw RejectedMemory::instruction();
        }

        if ($this->containsCardNumber($content)) {
            throw RejectedMemory::secret('card');
        }

        if ($this->containsIban($content)) {
            throw RejectedMemory::secret('iban');
        }

        if (Str::isMatch(self::API_KEY_PATTERNS, $content)) {
            throw RejectedMemory::secret('api_key');
        }

        if (Str::isMatch(config('memory.password_patterns'), $content)) {
            throw RejectedMemory::secret('password');
        }
    }

    /**
     * Determine whether the content holds a number that passes the Luhn check.
     */
    protected function containsCardNumber(string $content): bool
    {
        return Str::matchAll('/\b\d(?:[ -]?\d){12,18}\b/', $content)
            ->contains(fn (string $match): bool => $this->passesLuhn((string) Str::replaceMatches('/\D/', '', $match)));
    }

    /**
     * Determine whether the digits pass the Luhn check.
     */
    protected function passesLuhn(string $digits): bool
    {
        return Str::of($digits)->split(1)->reverse()->values()
            ->map(fn (string $digit, int $position): int => (int) $digit * ($position % 2 === 1 ? 2 : 1))
            ->sum(fn (int $value): int => $value > 9 ? $value - 9 : $value) % 10 === 0;
    }

    /**
     * Determine whether the content holds an IBAN with a valid checksum.
     */
    protected function containsIban(string $content): bool
    {
        return Str::matchAll('/\b[A-Z]{2}\d{2}(?:\s?[A-Z0-9]){11,30}\b/', Str::upper($content))
            ->contains(function (string $match): bool {
                $iban = Str::remove(' ', $match);
                $rearranged = Str::substr($iban, 4).Str::substr($iban, 0, 4);

                return $this->mod97((string) Str::replaceMatches('/[A-Z]/', fn (array $letter): string => (string) (ord($letter[0]) - 55), $rearranged)) === 1;
            });
    }

    /**
     * Compute the remainder of a long numeric string divided by 97.
     */
    protected function mod97(string $numeric): int
    {
        return Str::of($numeric)->split(7)->reduce(fn (int $remainder, string $chunk): int => (int) ($remainder.$chunk) % 97, 0);
    }
}
