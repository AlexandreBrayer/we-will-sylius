<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain;

use App\Yack\Catalog\Domain\ProductFacetValues;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ProductFacetValues::class)]
final class ProductFacetValuesTest extends TestCase
{
    public function testValues(): void
    {
        $values = new ProductFacetValues('Yack', 'Evolys', 8.0, 'R32');

        $this->assertSame('Yack', $values->brand);
        $this->assertSame('Evolys', $values->range);
        $this->assertSame(8.0, $values->powerKw);
        $this->assertSame('R32', $values->refrigerant);
        $this->assertNull((new ProductFacetValues())->powerKw);
    }
}
