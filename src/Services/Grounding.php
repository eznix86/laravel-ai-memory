<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Services;

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

        if ($words === []) {
            return false;
        }

        $own = $this->words($this->withoutQuotes($message));

        return count(array_intersect($words, $own)) / count($words) >= self::MIN_OVERLAP;
    }

    /**
     * Remove quoted lines, quoted strings and code blocks from the message.
     */
    public function withoutQuotes(string $message): string
    {
        return (string) preg_replace([
            '/```.*?```/s',
            '/^\s*>.*$/m',
            '/"[^"]*"|“[^”]*”|«[^»]*»/u',
            "/(?<=^|[\\s:(])'[^']+'(?=$|[\\s.,;:!?)])/m",
        ], ' ', $message);
    }

    /**
     * Get the distinct meaningful words of the text.
     *
     * @return list<string>
     */
    protected function words(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($text), $matches);

        $words = array_filter(
            $matches[0],
            fn (string $word): bool => (mb_strlen($word) >= 3 || is_numeric($word)) && ! in_array($word, self::IGNORED, true),
        );

        return array_values(array_unique(array_map(
            fn (string $word): string => mb_strlen($word) > 3 ? (string) preg_replace('/s$/u', '', $word) : $word,
            $words,
        )));
    }
}
