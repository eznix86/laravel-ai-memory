<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Console;

use Eznix86\AI\Memory\Jobs\ConsolidateMemories;
use Eznix86\AI\Memory\Models\AgentMemory;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class ConsolidateCommand extends Command
{
    protected $signature = 'memory:consolidate {--sync : Run the consolidation now instead of queueing it}';

    protected $description = 'Merge memories that share a subject and archive the ones that faded, for every owner';

    public function handle(): void
    {
        AgentMemory::query()
            ->select(['owner_type', 'owner_id'])
            ->distinct()
            ->get()
            ->each(function (AgentMemory $row): void {
                $class = Relation::getMorphedModel($row->owner_type) ?? $row->owner_type;

                $owner = class_exists($class) ? $class::find($row->owner_id) : null;

                if (! $owner instanceof Model) {
                    return;
                }

                $this->option('sync') ? ConsolidateMemories::dispatchSync($owner) : ConsolidateMemories::dispatch($owner);

                $this->line("Consolidating {$row->owner_type} #{$row->owner_id}.");
            });
    }
}
