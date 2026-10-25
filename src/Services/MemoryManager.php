<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Services;

use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\Exceptions\RejectedMemory;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\MemoryWrite;
use Eznix86\AI\Memory\Models\AgentMemory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class MemoryManager
{
    /**
     * Create a new memory manager instance.
     */
    public function __construct(
        protected MemoryWriter $writer,
    ) {}

    /**
     * List the live memories in the given context.
     *
     * @return Collection<int, AgentMemory>
     */
    public function list(MemoryContext $context, ?MemoryKind $kind = null, ?string $subject = null, ?string $search = null): Collection
    {
        return AgentMemory::query()
            ->forContext($context)
            ->live()
            ->when($kind, fn (Builder $query) => $query->where('kind', $kind))
            ->when($subject, fn (Builder $query) => $query->where('subject', $subject))
            ->when($search, fn (Builder $query) => $query->whereLike('content', "%{$search}%"))
            ->strongestFirst()
            ->get();
    }

    /**
     * Add a fact written by a person.
     *
     * @param  array{kind: MemoryKind|string, content: string, subject?: string|null, importance?: float|null, expires_at?: \DateTimeInterface|null, personal?: bool|null}  $fact
     *
     * @throws RejectedMemory
     */
    public function add(MemoryContext $context, Model $creator, array $fact): MemoryWrite
    {
        return $this->writer->write($context, [...$fact, 'source' => 'user', 'creator' => $creator]);
    }

    /**
     * Correct the content of a memory, which marks it as confirmed by the editor.
     *
     * @throws RejectedMemory
     */
    public function edit(AgentMemory $memory, string $content, Model $editor): AgentMemory
    {
        $this->writer->validate(['kind' => $memory->kind, 'content' => $content, 'source' => $memory->source]);

        $memory->content = $content;
        $memory->creator()->associate($editor);
        $memory->save();

        return $memory;
    }

    /**
     * Delete a memory for good.
     */
    public function delete(AgentMemory $memory): void
    {
        $memory->delete();
    }

    /**
     * Export every memory that belongs to the user, including archived and superseded ones.
     *
     * @return array<int, array<string, mixed>>
     */
    public function export(Model $user): array
    {
        return AgentMemory::query()
            ->whereBelongsToUser($user)
            ->oldest('id')
            ->get()
            ->map(fn (AgentMemory $memory): array => $memory->makeHidden('embedding')->toArray())
            ->values()
            ->all();
    }

    /**
     * Delete every memory about a subject in the owner, whatever its state.
     */
    public function purge(Model $owner, string $subject): int
    {
        return AgentMemory::query()
            ->forOwner($owner)
            ->where('subject', $subject)
            ->delete();
    }
}
