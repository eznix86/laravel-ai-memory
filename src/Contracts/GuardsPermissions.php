<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Contracts;

interface GuardsPermissions
{
    /**
     * Determine whether the fact grants the agent a permission to act on its own.
     */
    public function grantsPermission(string $content): bool;
}
