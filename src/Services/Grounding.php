<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Grounding
{
    public const float MIN_OVERLAP = 0.5;

    /**
     * Words that carry no fact, including the ones a model adds when it rewrites a fact in the third person.
     *
     * @var list<string>
     */
    protected const array IGNORED = [
        'the', 'and', 'for', 'are', 'our', 'that', 'this', 'with', 'from', 'has', 'have', 'was', 'were', 'will', 'its', 'their', 'they',
        'them', 'you', 'your', 'not', 'now', 'all', 'any', 'can', 'should', 'would', 'into', 'about', 'also', 'only', 'than', 'then',
        'user', 'users', 'team', 'teams', 'prefers', 'prefer', 'wants', 'want', 'likes', 'like', 'asked', 'asks', 'said', 'says',
    ];

    /**
     * Determine whether enough of the fact comes from the user's own words, not from quotes or the reply.
     */
    public function isGrounded(string $fact, string $message): bool
    {
        $words = $this->words($fact);

        if ($words->isEmpty()) {
            return false;
        }

        return $words->intersect($this->words($this->withoutQuotes($message)))->count() / $words->count() >= self::MIN_OVERLAP;
    }

    /**
     * Remove quoted lines, quoted strings and code blocks from the message.
     */
    public function withoutQuotes(string $message): string
    {
        return (string) Str::replaceMatches([
            '/```.*?```/s',
            '/^\s*>.*$/m',
            '/"[^"]*"|“[^”]*”|«[^»]*»/u',
            "/(?<=^|[\\s:(])'[^']+'(?=$|[\\s.,;:!?)])/m",
        ], ' ', $message);
    }

    /**
     * Get the distinct meaningful words of the text, without a plural "s".
     *
     * @return Collection<int, string>
     */
    protected function words(string $text): Collection
    {
        return Str::matchAll('/[\p{L}\p{N}]+/u', Str::lower($text))
            ->filter(fn (string $word): bool => Str::length($word) >= 3 || is_numeric($word))
            ->diff(self::IGNORED)
            ->map(fn (string $word): string => Str::length($word) > 3 ? Str::replaceEnd('s', '', $word) : $word)
            ->unique()
            ->values();
    }
}
