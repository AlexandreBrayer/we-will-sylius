<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Application;

use App\Yack\Catalog\Application\VariantStockReader;
use App\Yack\Catalog\Domain\StockLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
use Sylius\Component\Inventory\Model\StockableInterface;

/**
 * Needs Sylius (vendor/): runs on the full stack only, not on the agents' bench.
 *
 * @internal
 */
#[CoversClass(VariantStockReader::class)]
final class VariantStockReaderTest extends TestCase
{
    public function testNoVariantIsOutOfStock(): void
    {
        $reader = new VariantStockReader(self::checker(true));

        $this->assertSame(StockLevel::OUT_OF_STOCK, $reader->of(null)->level);
    }

    public function testTrackedVariantAvailableQuantity(): void
    {
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('isTracked')->willReturn(true);
        $variant->method('getOnHand')->willReturn(5);
        $variant->method('getOnHold')->willReturn(2);

        $stock = (new VariantStockReader(self::checker(true)))->of($variant);

        $this->assertSame(StockLevel::IN_STOCK, $stock->level);
        $this->assertSame(3, $stock->available);
        $this->assertSame('En stock', $stock->label());
    }

    public function testUnavailableVariantIsOutOfStock(): void
    {
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('isTracked')->willReturn(true);
        $variant->method('getOnHand')->willReturn(0);
        $variant->method('getOnHold')->willReturn(0);

        $stock = (new VariantStockReader(self::checker(false)))->of($variant);

        $this->assertSame(StockLevel::OUT_OF_STOCK, $stock->level);
        $this->assertSame('Rupture de stock', $stock->label());
    }

    public function testUntrackedVariantHasNoQuantity(): void
    {
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('isTracked')->willReturn(false);
        $variant->expects($this->never())->method('getOnHand');

        $stock = (new VariantStockReader(self::checker(true)))->of($variant);

        $this->assertNull($stock->available);
        $this->assertSame(StockLevel::IN_STOCK, $stock->level);
    }

    /** Hand-written fake of the Sylius availability checker. */
    private static function checker(bool $available): AvailabilityCheckerInterface
    {
        return new readonly class($available) implements AvailabilityCheckerInterface {
            public function __construct(private bool $available)
            {
            }

            public function isStockAvailable(StockableInterface $stockable): bool
            {
                return $this->available;
            }

            public function isStockSufficient(StockableInterface $stockable, int $quantity): bool
            {
                return $this->available;
            }
        };
    }
}
