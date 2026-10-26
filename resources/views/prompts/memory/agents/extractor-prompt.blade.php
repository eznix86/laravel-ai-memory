@if ($earlier !== [])
<earlier_messages>
@foreach ($earlier as $earlierMessage)
<{{ $earlierMessage['role'] }}>
{!! $earlierMessage['text'] !!}
</{{ $earlierMessage['role'] }}>

@endforeach
</earlier_messages>

@endif
<user>
{!! $message !!}
</user>

<assistant>
{!! $reply !!}
</assistant>
