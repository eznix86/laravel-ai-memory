<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory;

use Illuminate\Support\Str;

final class Prompt
{
    /**
     * Render a prompt view without the blank lines around it.
     *
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     */
    public static function render(string $view, array $data = []): string
    {
        return Str::trim(view($view, $data)->render());
    }
}
