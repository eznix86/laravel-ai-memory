<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Models;

use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\MemoryContext;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $tenant_type
 * @property string $tenant_id
 * @property string|null $user_type
 * @property string|null $user_id
 * @property MemoryKind $kind
 * @property string|null $subject
 * @property string $content
 * @property float $importance
 * @property string $source
 * @property string|null $creator_type
 * @property string|null $creator_id
 * @property int $recall_count
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property int|null $superseded_by
 * @property Carbon|null $archived_at
 * @property array<float>|null $embedding
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AgentMemory extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'importance' => 0.5,
        'recall_count' => 0,
    ];

    /**
     * Get the table associated with the model.
     */
    #[\Override]
    public function getTable(): string
    {
        return config('memory.table', 'agent_memories');
    }

    /**
     * Bootstrap the model and its traits.
     */
    #[\Override]
    protected static function booted(): void
    {
        static::saving(function (AgentMemory $memory): void {
            if ($memory->isDirty(['content', 'subject', 'kind'])) {
                $memory->embedding = Str::of($memory->embeddingText())->toEmbeddings();
            }
        });
    }

    /**
     * Get the text the embedding is generated from.
     */
    public function embeddingText(): string
    {
        return collect([$this->subject, "{$this->kind->value}: {$this->content}"])->filter()->implode(' · ');
    }

    /**
     * Get the tenant the memory belongs to.
     *
     * @return MorphTo<Model, $this>
     */
    public function tenant(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user the memory belongs to, if it is personal.
     *
     * @return MorphTo<Model, $this>
     */
    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the person who wrote or confirmed the memory.
     *
     * @return MorphTo<Model, $this>
     */
    public function creator(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the memory that replaced this one.
     *
     * @return BelongsTo<AgentMemory, $this>
     */
    public function successor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by');
    }

    /**
     * Limit the query to the memories visible in the given context.
     *
     * @param  Builder<AgentMemory>  $query
     */
    #[Scope]
    protected function forContext(Builder $query, MemoryContext $context): void
    {
        $query->where('tenant_type', $context->tenant->getMorphClass())
            ->where('tenant_id', (string) $context->tenant->getKey())
            ->where(function (Builder $query) use ($context): void {
                $query->whereNull('user_id');

                if ($context->user instanceof Model) {
                    $query->orWhere(fn (Builder $query) => $query->whereBelongsToUser($context->user));
                }
            });
    }

    /**
     * Limit the query to memories that belong to the given user.
     *
     * @param  Builder<AgentMemory>  $query
     */
    #[Scope]
    protected function whereBelongsToUser(Builder $query, Model $user): void
    {
        $query->where('user_type', $user->getMorphClass())->where('user_id', (string) $user->getKey());
    }

    /**
     * Limit the query to memories that are not superseded, archived or expired.
     *
     * @param  Builder<AgentMemory>  $query
     */
    #[Scope]
    protected function live(Builder $query): void
    {
        $query->whereNull('superseded_by')
            ->whereNull('archived_at')
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * Order the query from the strongest memory to the weakest.
     *
     * @param  Builder<AgentMemory>  $query
     */
    #[Scope]
    protected function strongestFirst(Builder $query): void
    {
        $query->orderByDesc('importance')->orderByRaw('coalesce(last_used_at, created_at) desc')->orderByDesc('id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => MemoryKind::class,
            'importance' => 'float',
            'recall_count' => 'integer',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'archived_at' => 'datetime',
            'embedding' => 'array',
        ];
    }
}
