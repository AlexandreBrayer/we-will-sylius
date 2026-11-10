<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Application;

use App\Yack\Catalog\Domain\CatalogProduct;
use App\Yack\Catalog\Domain\InvalidSourceProduct;
use App\Yack\Catalog\Domain\ProductCatalog;
use App\Yack\Catalog\Domain\SourceProduct;

/**
 * Hand-written fake of the port: the products of the shop given by code, a code containing "BAD" is
 * rejected; records what was checked, applied and committed.
 */
final class FakeProductCatalog implements ProductCatalog
{
    /** @var list<string> */
    public array $checked = [];

    /** @var list<string> */
    public array $applied = [];

    public bool $committed = false;

    /** @param array<string, CatalogProduct> $products */
    public function __construct(private readonly array $products = [])
    {
    }

    public function current(string $code): ?CatalogProduct
    {
        return $this->products[$code] ?? null;
    }

    public function check(SourceProduct $product): void
    {
        self::refuseBad($product);
        $this->checked[] = $product->code;
    }

    public function apply(SourceProduct $product): void
    {
        self::refuseBad($product);
        $this->applied[] = $product->code;
    }

    public function commit(): void
    {
        $this->committed = true;
    }

    private static function refuseBad(SourceProduct $product): void
    {
        if (str_contains($product->code, 'BAD')) {
            throw new InvalidSourceProduct(sprintf('Produit « %s » : catégorie ou marque « nope » inconnue.', $product->code));
        }
    }
}
