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
        return new self(__('memory::rejections.secret', ['kind' => __("memory::rejections.secrets.{$kind}")]));
    }

    /**
     * Create an exception for a fact phrased as an instruction.
     */
    public static function instruction(): self
    {
        return new self(__('memory::rejections.instruction'));
    }

    /**
     * Create an exception for a fact that grants the agent a permission.
     */
    public static function permission(): self
    {
        return new self(__('memory::rejections.permission'));
    }

    /**
     * Create an exception for a fact that fails validation.
     */
    public static function invalid(string $reason): self
    {
        return new self(__('memory::rejections.invalid', ['reason' => $reason]));
    }
}
