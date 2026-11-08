<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * Freedom of an administrator in the builder. `charte`: only the named values of the charte
 * (Charte); `libre_avertissement`: free values allowed (BlockStyle bounds), the builder warns;
 * `libre`: free values allowed without warning, and the only level that changes the level of
 * other administrators. Without an administrator (fixtures, commands), content is written in `libre`.
 */
enum BuilderLevel: string
{
    case Charte = 'charte';
    case LibreAvertissement = 'libre_avertissement';
    case Libre = 'libre';

    public function label(): string
    {
        return match ($this) {
            self::Charte => 'Charte',
            self::LibreAvertissement => 'Libre avec avertissement',
            self::Libre => 'Libre',
        };
    }

    /** Whether a style may hold values outside the charte (BlockStyle::deviations()). */
    public function allowsFreeValues(): bool
    {
        return self::Charte !== $this;
    }

    /** Whether the builder shows the « hors charte » warning under a free value. */
    public function warnsOnFreeValues(): bool
    {
        return self::LibreAvertissement === $this;
    }

    /** Whether the administrator may change the builder level of other administrators. */
    public function canManageLevels(): bool
    {
        return self::Libre === $this;
    }
}
