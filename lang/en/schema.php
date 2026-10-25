<?php

declare(strict_types=1);

return [
    'kind' => 'The kind of fact.',
    'content' => 'One fact, at most :max characters.',
    'subject' => 'The id of the one thing the fact is about, as type:id, such as customer:42 or project:apollo. Leave it out when the fact is general.',
    'importance' => 'How much the fact matters, from 0 to 1. The default is 0.5.',
    'memory_id' => 'The id of the memory, as shown after the # sign.',
    'query' => 'What to search for.',
    'include_archived' => 'Also search memories that were forgotten or faded. Use it only when the user asks about something from long ago.',
    'replace_id' => 'The id of the memory the new fact replaces, when the decision is update.',
    'note' => 'One note, at most :max characters.',
];
