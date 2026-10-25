<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Console;

use Eznix86\AI\Memory\Jobs\ConsolidateMemories;
use Eznix86\AI\Memory\Models\AgentMemory;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class ConsolidateCommand extends Command
{
    protected $signature = 'memory:consolidate
        {--sync : Run the consolidation now instead of queueing it}
        {--prune : Delete the memories of owners that no longer exist}';

    protected $description = 'Merge memories that share a subject and archive the ones that faded, for every owner';

    public function handle(): void
    {
        $missing = AgentMemory::query()
            ->select(['owner_type', 'owner_id'])
            ->distinct()
            ->get()
            ->reject(function (AgentMemory $row): bool {
                $class = Relation::getMorphedModel($row->owner_type) ?? $row->owner_type;
                $owner = class_exists($class) ? $class::find($row->owner_id) : null;

                if (! $owner instanceof Model) {
                    return false;
                }

                $this->option('sync') ? ConsolidateMemories::dispatchSync($owner) : ConsolidateMemories::dispatch($owner);
                $this->line("Consolidating {$row->owner_type} #{$row->owner_id}.");

                return true;
            });

        if ($missing->isEmpty()) {
            return;
        }

        $orphans = AgentMemory::query()->where(function (Builder $query) use ($missing): void {
            $missing->each(fn (AgentMemory $row) => $query->orWhere(fn (Builder $query) => $query->where('owner_type', $row->owner_type)->where('owner_id', $row->owner_id)));
        });

        if ($this->option('prune')) {
            $this->info("Deleted {$orphans->delete()} memories of {$missing->count()} owners that no longer exist.");

            return;
        }

        $this->warn("{$orphans->count()} memories belong to {$missing->count()} owners that no longer exist. Run with --prune to delete them.");
    }
}
