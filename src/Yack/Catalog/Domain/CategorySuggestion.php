<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/** A shop category proposed while the customer types (search box of the header). */
final readonly class CategorySuggestion
{
    public function __construct(
        public string $code,
        public string $name,
        public string $slug,
    ) {
    }
}
