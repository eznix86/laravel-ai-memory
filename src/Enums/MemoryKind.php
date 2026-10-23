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
     * Determine whether a memory of this kind belongs to one user rather than the whole tenant.
     */
    public function isPersonal(): bool
    {
        return $this === self::Preference;
    }
}
