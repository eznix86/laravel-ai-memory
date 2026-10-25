<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\PermissionGuards;

use Eznix86\AI\Memory\Contracts\GuardsPermissions;

class NoPermissionGuard implements GuardsPermissions
{
    /**
     * Let every fact through.
     */
    public function grantsPermission(string $content): bool
    {
        return false;
    }
}
