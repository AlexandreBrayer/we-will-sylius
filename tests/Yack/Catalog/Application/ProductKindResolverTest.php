<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Application;

use App\Yack\Catalog\Application\ProductKindResolver;
use App\Yack\Catalog\Domain\ProductKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;

/**
 * Needs Sylius (vendor/): runs on the full stack only, not on the agents' bench.
 *
 * @internal
 */
#[CoversClass(ProductKindResolver::class)]
final class ProductKindResolverTest extends TestCase
{
    public function testSparePartFromTheMainTaxonAncestors(): void
    {
        $root = $this->taxon('clik_yack_parts', null);
        $main = $this->taxon('parts_hydraulique', $root);

        $this->assertSame(ProductKind::SparePart, (new ProductKindResolver())->kindOf($this->product($main)));
    }

    public function testFinishedProductWhenTheMainTaxonIsTheBranchRoot(): void
    {
        $this->assertSame(ProductKind::Finished, (new ProductKindResolver())->kindOf($this->product($this->taxon('clik_yack', null))));
    }

    public function testTaxonWithoutCodeIsSkipped(): void
    {
        $root = $this->taxon('clik_yack', null);
        $main = $this->taxon(null, $root);

        $this->assertSame(ProductKind::Finished, (new ProductKindResolver())->kindOf($this->product($main)));
    }

    public function testOutsideBothBranches(): void
    {
        $this->assertNull((new ProductKindResolver())->kindOf($this->product($this->taxon('marque_yack', null))));
    }

    public function testNoMainTaxon(): void
    {
        $this->assertNull((new ProductKindResolver())->kindOf($this->product(null)));
    }

    private function taxon(?string $code, ?TaxonInterface $parent): TaxonInterface
    {
        $taxon = $this->createMock(TaxonInterface::class);
        $taxon->method('getCode')->willReturn($code);
        $taxon->method('getParent')->willReturn($parent);

        return $taxon;
    }

    private function product(?TaxonInterface $mainTaxon): ProductInterface
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getMainTaxon')->willReturn($mainTaxon);

        return $product;
    }
}
