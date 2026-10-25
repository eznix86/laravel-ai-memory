<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Enums;

enum MemoryKind: string
{
    case Preference = 'preference';
    case Process = 'process';
    case Correction = 'correction';
    case CustomerNote = 'customer_note';
    case Lesson = 'lesson';
    case Context = 'context';

    /**
     * Get the value of every kind.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return collect(self::cases())->map(fn (self $kind): string => $kind->value)->values()->all();
    }

    /**
     * Determine whether a memory of this kind belongs to one user rather than the whole owner.
     */
    public function isPersonal(): bool
    {
        return $this === self::Preference;
    }

    /**
     * Get the number of unused days after which a memory of this kind is half as strong.
     */
    public function halfLife(): int
    {
        return match ($this) {
            self::Preference => 180,
            self::Process, self::Correction => 365,
            self::CustomerNote => 120,
            self::Lesson => 90,
            self::Context => 21,
        };
    }
}
