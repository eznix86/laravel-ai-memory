<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Eznix86\AI\Memory\Agents\MemoryExtractor;

class TeamExtractor extends MemoryExtractor
{
    public function instructions(): string
    {
        return 'Pick out facts about the team only.';
    }
}
