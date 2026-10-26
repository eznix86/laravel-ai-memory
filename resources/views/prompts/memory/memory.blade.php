#{{ $memory->id }} [{{ $memory->kind->value }}]@if ($memory->archived_at) (archived)@endif {!! $memory->content !!}
