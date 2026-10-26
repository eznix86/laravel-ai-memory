<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Console;

use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\Exceptions\RejectedMemory;
use Eznix86\AI\Memory\MemoryContext;
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Services\MemoryWriter;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use stdClass;

class ImportV1Command extends Command
{
    protected $signature = 'memory:import-v1
        {--user-model=App\Models\User : The model that the user_id of the old rows points to}
        {--table=memories : The table of version 1}
        {--kind=profile : The kind of every imported memory}
        {--dry-run : Count what would be imported, and write nothing}';

    protected $description = 'Copy the memories of version 1 into the memory table of version 2';

    public function handle(MemoryWriter $writer): int
    {
        $table = (string) $this->option('table');
        $class = (string) $this->option('user-model');
        $kind = MemoryKind::tryFrom((string) $this->option('kind'));

        if (! Schema::hasTable($table)) {
            $this->error("The table [{$table}] does not exist.");

            return self::FAILURE;
        }

        if (! is_subclass_of($class, Model::class)) {
            $this->error("The user model [{$class}] is not an Eloquent model.");

            return self::FAILURE;
        }

        if (! $kind instanceof MemoryKind) {
            $this->error('The kind must be one of: '.implode(', ', MemoryKind::values()).'.');

            return self::FAILURE;
        }

        $results = collect();

        DB::table($table)->orderBy('id')->chunkById(100, function (Collection $rows) use ($results, $class, $kind, $writer): void {
            $rows->each(fn (stdClass $row) => $results->push($this->import($row, $class, $kind, $writer)));
        });

        $this->table(['Result', 'Rows'], $results->countBy()->sortKeys()->map(fn (int $count, string $result): array => [$result, $count])->values()->all());

        return self::SUCCESS;
    }

    /**
     * Determine whether the old user id can be a key of the user model, so a query never fails on its type.
     *
     * @param  class-string<Model>  $class
     */
    protected function fitsKey(string $class, string $id): bool
    {
        $traits = class_uses_recursive($class);

        return match (true) {
            in_array(HasUuids::class, $traits, true) => Str::isUuid($id),
            in_array(HasUlids::class, $traits, true) => Str::isUlid($id),
            (new $class)->getKeyType() === 'int' => ctype_digit($id),
            default => true,
        };
    }

    /**
     * Write one old row for its user, and say what happened to it.
     *
     * @param  class-string<Model>  $class
     */
    protected function import(stdClass $row, string $class, MemoryKind $kind, MemoryWriter $writer): string
    {
        $source = "v1:{$row->id}";

        if ($row->user_id === null) {
            return 'skipped: no user';
        }

        if (AgentMemory::query()->where('source', $source)->exists()) {
            return 'skipped: already imported';
        }

        $user = $this->fitsKey($class, (string) $row->user_id) ? $class::query()->find($row->user_id) : null;

        if (! $user instanceof Model) {
            return 'skipped: user not found';
        }

        if ($this->option('dry-run')) {
            return 'would be imported';
        }

        try {
            $write = $writer->write(new MemoryContext(user: $user), ['kind' => $kind, 'content' => (string) $row->content, 'source' => $source, 'personal' => true]);
        } catch (RejectedMemory $rejection) {
            return "rejected: {$rejection->getMessage()}";
        }

        if ($row->created_at !== null && $write->memory->source === $source) {
            $write->memory->forceFill(['created_at' => $row->created_at])->saveQuietly();
        }

        return $write->outcome->value;
    }
}
