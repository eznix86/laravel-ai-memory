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

    protected $description = 'Merge memories that share a subject and archive the ones that faded, for every tenant';

    public function handle(): void
    {
        AgentMemory::query()
            ->select(['tenant_type', 'tenant_id'])
            ->distinct()
            ->get()
            ->each(function (AgentMemory $row): void {
                $class = Relation::getMorphedModel($row->tenant_type) ?? $row->tenant_type;

                $tenant = class_exists($class) ? $class::find($row->tenant_id) : null;

                if (! $tenant instanceof Model) {
                    return;
                }

                $this->option('sync') ? ConsolidateMemories::dispatchSync($tenant) : ConsolidateMemories::dispatch($tenant);

                $this->line("Consolidating {$row->tenant_type} #{$row->tenant_id}.");
            });
    }
}
