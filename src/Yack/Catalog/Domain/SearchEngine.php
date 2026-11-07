<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * Engine answering the shop search: Meilisearch (relevance, typos, synonyms) or the database
 * (LIKE on name, code, brand and range), which is also the fallback when Meilisearch is down.
 */
enum SearchEngine: string
{
    case Meilisearch = 'meilisearch';
    case Database = 'database';

    /** Engine named by the configuration (YACK_SEARCH_ENGINE); any other value means the database. */
    public static function fromConfig(string $value): self
    {
        return self::tryFrom(strtolower(trim($value))) ?? self::Database;
    }
}
