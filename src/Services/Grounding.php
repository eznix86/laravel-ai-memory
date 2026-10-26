<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Grounding
{
    public const float MIN_OVERLAP = 0.5;

    /**
     * Determine whether enough of the fact comes from the user's own words, not from quotes or the reply.
     */
    public function isGrounded(string $fact, string $message): bool
    {
        $units = $this->units($fact);

        if ($units->isEmpty()) {
            return false;
        }

        return $units->intersect($this->units($this->withoutQuotes($message)))->count() / $units->count() >= self::MIN_OVERLAP;
    }

    /**
     * Remove quoted lines, quoted strings and code blocks from the message.
     */
    public function withoutQuotes(string $message): string
    {
        return (string) Str::replaceMatches([
            '/```.*?```/s',
            '/^\s*>.*$/m',
            '/"[^"]*"|“[^”]*”|«[^»]*»|「[^」]*」|『[^』]*』/u',
            "/(?<=^|[\\s:(])'[^']+'(?=$|[\\s.,;:!?)])/m",
        ], ' ', $message);
    }

    /**
     * Get the distinct units of the text: each Chinese or Japanese character, which carries meaning alone, and pairs of letters or digits elsewhere.
     *
     * @return Collection<int, string>
     */
    protected function units(string $text): Collection
    {
        return Str::matchAll('/[\p{Han}\p{Hiragana}\p{Katakana}]|(?:(?![\p{Han}\p{Hiragana}\p{Katakana}])[\p{L}\p{N}])+/u', Str::lower($text))
            ->flatMap(fn (string $run): array => Str::length($run) <= 2
                ? [$run]
                : Collection::times(Str::length($run) - 1, fn (int $position): string => Str::substr($run, $position - 1, 2))->all())
            ->unique()
            ->values();
    }
}
