<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * Codes of the products matching a search, most relevant first (one page), and the total
 * number of matching products. No price: the shop reads the products from the catalogue API,
 * which shows or hides prices according to the customer profile.
 */
final readonly class SearchResult
{
    /**
     * @param list<string> $codes
     */
    public function __construct(
        public array $codes,
        public int $total,
        public SearchEngine $engine,
    ) {
    }

    public static function none(SearchEngine $engine): self
    {
        return new self([], 0, $engine);
    }
}
