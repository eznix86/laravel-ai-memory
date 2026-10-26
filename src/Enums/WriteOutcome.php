<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Enums;

enum WriteOutcome: string
{
    case Added = 'added';
    case Updated = 'updated';
    case Known = 'known';
}
