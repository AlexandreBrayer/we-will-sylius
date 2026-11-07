<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/** A product proposed while the customer types (search box of the header). */
final readonly class ProductSuggestion
{
    /**
     * @param string|null $imagePath path of the main image in the media storage (as stored by Sylius)
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $slug,
        public ?string $brand = null,
        public ?string $imagePath = null,
    ) {
    }
}
