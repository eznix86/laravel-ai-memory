Subject: {!! $subject !!}

Memories, oldest first:
@foreach ($memories as $memory)
{{ $memory->created_at->toDateString() }} @include('prompts.memory.memory')
@endforeach
