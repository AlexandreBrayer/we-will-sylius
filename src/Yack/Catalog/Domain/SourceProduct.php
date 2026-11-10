<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * A product as published by the Yack PIM/ERP (Yack is the master of this data).
 */
final readonly class SourceProduct
{
    /**
     * @param list<string>                         $taxonCodes        additional taxons (brand, ...)
     * @param array<string, string|int|float|bool> $attributes        attribute code => value
     * @param string|null                          $imagePath         image to show: the product photo, or the generic visual while the photo is missing
     * @param string|null                          $fallbackImagePath generic visual of the product, when there is one
     */
    public function __construct(
        public string $code,
        public string $name,
        public bool $active,
        public ProductKind $kind,
        public string $mainTaxonCode,
        public int $priceInCents,
        public int $stock,
        public array $taxonCodes = [],
        public array $attributes = [],
        public ?string $shortDescription = null,
        public ?string $description = null,
        public ?string $imagePath = null,
        public ?string $fallbackImagePath = null,
    ) {
        if ('' === trim($code)) {
            throw new InvalidSourceProduct('Code produit manquant.');
        }
        if ('' === trim($name)) {
            throw new InvalidSourceProduct(sprintf('Produit « %s » : nom manquant.', $code));
        }
        if ($priceInCents < 0) {
            throw new InvalidSourceProduct(sprintf('Produit « %s » : prix négatif (%s).', $code, ProductChange::price($priceInCents)));
        }
        if ($stock < 0) {
            throw new InvalidSourceProduct(sprintf('Produit « %s » : stock négatif (%d).', $code, $stock));
        }
    }

    /** True when the image to show is the product photo itself, not its generic visual. */
    public function hasPhoto(): bool
    {
        return null !== $this->imagePath && null !== $this->fallbackImagePath && $this->imagePath !== $this->fallbackImagePath;
    }
}
