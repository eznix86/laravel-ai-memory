<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Agents\Concerns;

use Laravel\Ai\Promptable;

trait AsksMemoryModel
{
    use Promptable;

    /**
     * Prompt the configured memory model and return its structured answer.
     *
     * @return array<string, mixed>
     */
    public function ask(string $prompt): array
    {
        return $this->prompt($prompt, provider: config('memory.provider'), model: config('memory.model'), timeout: config('memory.timeout'))->structured;
    }
}
