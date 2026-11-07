<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Doctrine;

use App\Yack\Catalog\Domain\SearchVocabulary;

/**
 * LIKE patterns of the database search: "%", "_" and "\" typed by the customer are matched as
 * such, and each word also matches its trade synonyms (SearchVocabulary).
 */
final class SearchPatterns
{
    public static function escape(string $text): string
    {
        return addcslashes(mb_strtolower($text), '%_\\');
    }

    public static function contains(string $text): string
    {
        return '%' . self::escape($text) . '%';
    }

    public static function startsWith(string $text): string
    {
        return self::escape($text) . '%';
    }

    /**
     * "Contains" patterns of a word and of its synonyms.
     *
     * @return list<string>
     */
    public static function alternatives(string $word): array
    {
        return array_values(array_unique(array_map(self::contains(...), [$word, ...SearchVocabulary::synonymsOf($word)])));
    }
}
