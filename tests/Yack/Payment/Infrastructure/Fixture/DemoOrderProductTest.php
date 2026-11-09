<?php

declare(strict_types=1);

namespace App\Tests\Yack\Payment\Infrastructure\Fixture;

use App\Yack\Payment\Infrastructure\Fixture\DemoOrderProduct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DemoOrderProduct::class)]
final class DemoOrderProductTest extends TestCase
{
    public function testItKeepsTheCodeThePriceAndTheKind(): void
    {
        $product = new DemoOrderProduct('YKP-FIL-KAZ', 1_990, true);

        $this->assertSame(['YKP-FIL-KAZ', 1_990, true], [$product->variantCode, $product->unitPrice, $product->sparePart]);
    }
}
