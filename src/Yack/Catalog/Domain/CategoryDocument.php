<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/** What the search engine knows of a shop category (Clik&Yack or Clik&Yack Parts branch). */
final readonly class CategoryDocument
{
    /**
     * @param list<string> $path names of the ancestors, from the menu root down (the category excluded)
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $slug,
        public ProductKind $kind,
        public array $path = [],
    ) {
    }
}
