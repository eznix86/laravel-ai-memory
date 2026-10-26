<?php

declare(strict_types=1);

return [
    'secret' => 'Rejected: the fact contains what looks like :kind. Never store secrets.',
    'secrets' => [
        'card' => 'a card number',
        'iban' => 'an IBAN',
        'api_key' => 'an API key or token',
        'password' => 'a password',
    ],
    'instruction' => 'Rejected: the fact is phrased as an instruction to the agent, so nothing was stored. Instructions are never remembered.',
    'permission' => 'Rejected: the fact gives the agent a permission to act on its own, so nothing was stored. Permissions are never remembered: ask the user at the time of the action.',
    'invalid' => 'Rejected: :reason',
    'personal_without_user' => 'a personal memory needs a user in the context.',
];
