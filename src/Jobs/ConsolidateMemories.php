<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Jobs;

use Eznix86\AI\Memory\Agents\MemoryConsolidator;
use Eznix86\AI\Memory\Exceptions\RejectedMemory;
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Prompt;
use Eznix86\AI\Memory\Services\MemoryWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConsolidateMemories implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Model $tenant,
    ) {
        $this->onQueue(config('memory.queue'));
    }

    /**
     * Merge the subjects with many memories, then archive the memories that faded.
     */
    public function handle(MemoryWriter $writer): void
    {
        AgentMemory::query()
            ->forTenant($this->tenant)
            ->live()
            ->whereNotNull('subject')
            ->select(['subject', 'user_type', 'user_id'])
            ->groupBy(['subject', 'user_type', 'user_id'])
            ->havingRaw('count(*) >= ?', [config('memory.consolidation_threshold')])
            ->get()
            ->each(fn (AgentMemory $group) => $this->merge($writer, $this->memoriesLike($group)));

        AgentMemory::query()
            ->forTenant($this->tenant)
            ->live()
            ->whereNull('creator_id')
            ->weakerThan(config('memory.archive_below'))
            ->toBase()
            ->update(['archived_at' => now()]);
    }

    /**
     * Get the live memories that share the subject and the owner of the group.
     *
     * @return Collection<int, AgentMemory>
     */
    protected function memoriesLike(AgentMemory $group): Collection
    {
        return AgentMemory::query()
            ->forTenant($this->tenant)
            ->live()
            ->where('subject', $group->subject)
            ->where('user_type', $group->user_type)
            ->where('user_id', $group->user_id)
            ->oldest()
            ->oldest('id')
            ->get();
    }

    /**
     * Replace the memories with one merged note.
     *
     * @param  Collection<int, AgentMemory>  $memories
     */
    protected function merge(MemoryWriter $writer, Collection $memories): void
    {
        $first = $memories->firstOrFail();

        $kind = $memories->countBy(fn (AgentMemory $memory): string => $memory->kind->value)->sortDesc()->keys()->first();

        $answer = (new MemoryConsolidator)->ask(Prompt::render('prompts.memory.agents.consolidator-prompt', [
            'subject' => $first->subject,
            'memories' => $memories,
        ]));

        try {
            $fact = $writer->validate([
                'kind' => $kind,
                'content' => $answer['content'] ?? null,
                'importance' => $answer['importance'] ?? null,
                'source' => 'consolidation',
            ]);
        } catch (RejectedMemory $exception) {
            Log::warning('Memory consolidation kept the originals because the merged note was rejected.', [
                'tenant_type' => $first->tenant_type,
                'tenant_id' => $first->tenant_id,
                'subject' => $first->subject,
                'reason' => $exception->getMessage(),
                'note' => $answer['content'] ?? null,
            ]);

            return;
        }

        DB::transaction(function () use ($memories, $first, $fact): void {
            $merged = AgentMemory::create([
                'tenant_type' => $first->tenant_type,
                'tenant_id' => $first->tenant_id,
                'user_type' => $first->user_type,
                'user_id' => $first->user_id,
                'kind' => $fact['kind'],
                'subject' => $first->subject,
                'content' => $fact['content'],
                'importance' => $fact['importance'] ?? $memories->max('importance'),
                'source' => 'consolidation',
                'last_used_at' => $memories->max('last_used_at'),
            ]);

            AgentMemory::query()->whereKey($memories->modelKeys())->update(['superseded_by' => $merged->id]);
        });
    }
}
