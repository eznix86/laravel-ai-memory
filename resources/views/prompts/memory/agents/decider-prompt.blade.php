New fact:
[{{ $memory->kind->value }}] {!! $memory->content !!}

Stored memories:
@foreach ($candidates as $memory)
@include('prompts.memory.memory')
@endforeach
