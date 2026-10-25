<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Skills;

use Eznix86\AI\Memory\Prompt;
use Laravel\Ai\Skills\Skill;

final class MemorySkill
{
    /**
     * Create the skill that teaches the agent how to use its memory tools.
     */
    public static function make(): Skill
    {
        return new Skill(
            'memory',
            Prompt::render('prompts.memory.skill.description'),
            view('prompts.memory.skill.instructions'),
        );
    }
}
