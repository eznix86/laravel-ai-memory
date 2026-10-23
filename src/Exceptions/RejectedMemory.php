<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Exceptions;

use InvalidArgumentException;

class RejectedMemory extends InvalidArgumentException
{
    /**
     * Create an exception for a fact that holds a secret.
     */
    public static function secret(string $kind): self
    {
        return new self("Rejected: the fact contains what looks like {$kind}. Never store secrets.");
    }

    /**
     * Create an exception for a fact phrased as an instruction.
     */
    public static function instruction(): self
    {
        return new self('Rejected: the fact is phrased as an instruction to the agent, so nothing was stored. Instructions are never remembered.');
    }

    /**
     * Create an exception for a fact that fails validation.
     */
    public static function invalid(string $reason): self
    {
        return new self("Rejected: {$reason}");
    }
}
