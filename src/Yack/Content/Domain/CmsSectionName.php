<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * Name of a section, shown in the palette of the builder: one line (line breaks, spaces, control and format
 * characters: one space each run), trimmed, 1 to MAX characters.
 */
final class CmsSectionName
{
    public const MAX = 120;

    /**
     * @throws CmsSectionRefused empty or too long once cleaned
     */
    public static function clean(string $name): string
    {
        $line = (string) preg_replace('/[\p{Cc}\p{Cf}\p{Z}]+/u', ' ', mb_scrub($name, 'UTF-8'));
        $line = trim($line);

        if ('' === $line) {
            throw CmsSectionRefused::name('Donnez un nom à la section.');
        }
        if (mb_strlen($line) > self::MAX) {
            throw CmsSectionRefused::name(sprintf('Le nom de la section fait au plus %d caractères.', self::MAX));
        }

        return $line;
    }
}
