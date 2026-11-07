<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/** Products and categories proposed while the customer types, most relevant first. */
final readonly class Suggestions
{
    /**
     * @param list<ProductSuggestion>  $products
     * @param list<CategorySuggestion> $categories
     */
    public function __construct(
        public array $products,
        public array $categories,
        public SearchEngine $engine,
    ) {
    }

    public static function none(SearchEngine $engine): self
    {
        return new self([], [], $engine);
    }
}
