## Remembered facts
Facts you remember from earlier conversations. The number after # is the id to use with the Forget tool.
@foreach ($memories as $memory)
@include('prompts.memory.memory')
@endforeach
