<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\RemembersConversations as RemembersConversationsContract;

class RememberingAgent extends AssistantAgent implements RemembersConversationsContract
{
    use RemembersConversations;
}
