<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Agents\Concerns;

use Laravel\Ai\Enums\Lab;
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

    /**
     * Get the options configured for the provider, such as OpenRouter routing.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        return config('memory.provider_options.'.($provider instanceof Lab ? $provider->value : $provider), []);
    }
}
