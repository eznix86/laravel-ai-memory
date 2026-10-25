<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\PermissionGuards;

use Eznix86\AI\Memory\Agents\PermissionChecker;
use Eznix86\AI\Memory\Contracts\GuardsPermissions;
use Eznix86\AI\Memory\Prompt;

class AgentPermissionGuard implements GuardsPermissions
{
    /**
     * Ask a text model with structured output whether the fact grants the agent a permission.
     */
    public function grantsPermission(string $content): bool
    {
        $answer = (new PermissionChecker)->ask(Prompt::render('prompts.memory.agents.permission-prompt', ['fact' => $content]));

        return ($answer['grants_permission'] ?? false) === true;
    }
}
