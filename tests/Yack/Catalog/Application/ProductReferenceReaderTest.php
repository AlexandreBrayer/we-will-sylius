<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Application;

use App\Yack\Catalog\Application\ProductReferenceReader;
use App\Yack\Catalog\Domain\ProductReference;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;

/**
 * Needs Sylius (vendor/): runs on the full stack only, not on the agents' bench.
 *
 * @internal
 */
#[CoversClass(ProductReferenceReader::class)]
final class ProductReferenceReaderTest extends TestCase
{
    public function testFindsTheEnabledProductOfThePrintedReference(): void
    {
        $product = $this->product('SRK25ZS-W', true, [
            'marque' => 'MHI',
            ProductReference::DOCUMENTATION_ATTRIBUTE => 'Documentation Produit, Notice diagnostic',
        ]);
        $reader = new ProductReferenceReader($this->repository(['SRK25ZS-W' => $product]), 'fr_FR');

        $reference = $reader->find('srk25zs – w');

        $this->assertInstanceOf(ProductReference::class, $reference);
        $this->assertSame('SRK25ZS-W', $reference->code);
        $this->assertSame('SRK25ZS-W Mural - 2,5kW - R32', $reference->name);
        $this->assertSame('srk25zs-w-mural', $reference->slug);
        $this->assertSame('MHI', $reference->brand);
        $this->assertSame(['Documentation Produit', 'Notice diagnostic'], $reference->documents);
    }

    public function testTriesTheSpaceReadAsADash(): void
    {
        $reader = new ProductReferenceReader($this->repository(['SRK25ZS-W' => $this->product('SRK25ZS-W', true, [])]), 'fr_FR');

        $reference = $reader->find('SRK25ZS W');

        $this->assertSame('SRK25ZS-W', $reference?->code);
        $this->assertNull($reference?->brand);
        $this->assertSame([], $reference?->documents);
    }

    public function testDisabledOrUnknownProductIsNotProposed(): void
    {
        $reader = new ProductReferenceReader($this->repository(['SRK25ZS-W' => $this->product('SRK25ZS-W', false, [])]), 'fr_FR');

        $this->assertNull($reader->find('SRK25ZS-W'));
        $this->assertNull($reader->find('YK-PAE-M06'));
        $this->assertNull($reader->find('Unité intérieure de climatiseur réversible mural'));
    }

    /** @param array<string, ProductInterface> $products */
    private function repository(array $products): ProductRepositoryInterface
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->method('findOneByCode')->willReturnCallback(static fn (string $code): ?ProductInterface => $products[$code] ?? null);

        return $repository;
    }

    /** @param array<string, string> $attributes */
    private function product(string $code, bool $enabled, array $attributes): ProductInterface
    {
        $values = [];
        foreach ($attributes as $attributeCode => $value) {
            $attributeValue = $this->createMock(ProductAttributeValueInterface::class);
            $attributeValue->method('getCode')->willReturn($attributeCode);
            $attributeValue->method('getValue')->willReturn($value);
            $values[] = $attributeValue;
        }

        $product = $this->createMock(ProductInterface::class);
        $product->method('getCode')->willReturn($code);
        $product->method('isEnabled')->willReturn($enabled);
        $product->method('getName')->willReturn('SRK25ZS-W Mural - 2,5kW - R32');
        $product->method('getSlug')->willReturn('srk25zs-w-mural');
        $product->method('getAttributes')->willReturn(new ArrayCollection($values));

        return $product;
    }
}
