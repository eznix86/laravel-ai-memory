<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\PermissionGuards;

use Eznix86\AI\Memory\Contracts\GuardsPermissions;
use Eznix86\AI\Memory\Prompt;
use Laravel\Ai\Classification;
use Laravel\Ai\Classification\Boolean;
use Laravel\Ai\Responses\Data\BooleanAnswer;

class ClassificationPermissionGuard implements GuardsPermissions
{
    public const float THRESHOLD = 0.5;

    /**
     * Ask a classification model whether the fact grants the agent a permission.
     */
    public function grantsPermission(string $content): bool
    {
        $answer = Classification::of($content)
            ->question('grants_permission', new Boolean(
                Prompt::render('prompts.memory.classification.permission.question'),
                [
                    'true' => Prompt::render('prompts.memory.classification.permission.true'),
                    'false' => Prompt::render('prompts.memory.classification.permission.false'),
                ],
            ))
            ->timeout(config('memory.timeout'))
            ->classify(config('memory.classification_provider'), config('memory.classification_model'))
            ->answer('grants_permission');

        return $answer instanceof BooleanAnswer && $answer->isTrue(self::THRESHOLD);
    }
}
