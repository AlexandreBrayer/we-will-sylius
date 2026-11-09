<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain;

use App\Yack\Catalog\Domain\StockLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(StockLevel::class)]
final class StockLevelTest extends TestCase
{
    #[DataProvider('provideLevels')]
    public function testLevelAndLabel(string $expectedLevel, string $expectedLabel, bool $tracked, ?int $available, bool $inStock): void
    {
        $stock = StockLevel::of($tracked, $available, $inStock);

        $this->assertSame($expectedLevel, $stock->level);
        $this->assertSame($expectedLabel, $stock->label());
    }

    /**
     * @return \Iterator<string, array{string, string, bool, int|null, bool}>
     */
    public static function provideLevels(): \Iterator
    {
        yield '12 available' => [StockLevel::IN_STOCK, 'En stock', true, 12, true];
        yield '1 available' => [StockLevel::IN_STOCK, 'En stock', true, 1, true];
        yield '0 tracked, not available' => [StockLevel::OUT_OF_STOCK, 'Rupture de stock', true, 0, false];
        yield '3 tracked but not available' => [StockLevel::OUT_OF_STOCK, 'Rupture de stock', true, 3, false];
        yield 'not tracked, available' => [StockLevel::IN_STOCK, 'En stock', false, null, true];
    }

    public function testNegativeAvailableIsZero(): void
    {
        $stock = StockLevel::of(true, -2, false);

        $this->assertSame(0, $stock->available);
        $this->assertTrue($stock->tracked);
        $this->assertFalse($stock->inStock);
    }

    public function testUntrackedStockHasNoQuantity(): void
    {
        $stock = StockLevel::of(false, 25, true);

        $this->assertNull($stock->available);
        $this->assertFalse($stock->tracked);
    }

    public function testNoVariantIsOutOfStock(): void
    {
        $stock = StockLevel::noVariant();

        $this->assertSame(StockLevel::OUT_OF_STOCK, $stock->level);
        $this->assertSame(
            ['tracked' => false, 'available' => null, 'inStock' => false, 'label' => 'Rupture de stock', 'level' => 'rupture'],
            $stock->toArray(),
        );
    }

    public function testWithdrawnKeepsTheQuantityAndChangesTheLevel(): void
    {
        $stock = StockLevel::of(true, 3, true);
        $withdrawn = $stock->withdrawn();

        $this->assertSame(StockLevel::WITHDRAWN, $withdrawn->level);
        $this->assertSame('Plus commercialisée', $withdrawn->label());
        $this->assertSame(
            ['tracked' => true, 'available' => 3, 'inStock' => true, 'label' => 'Plus commercialisée', 'level' => 'plus_commercialisee'],
            $withdrawn->toArray(),
        );
        $this->assertSame('En stock', $stock->label());
    }

    public function testToArrayKeepsTheApiContractPlusTheLevel(): void
    {
        $this->assertSame(
            ['tracked' => true, 'available' => 3, 'inStock' => true, 'label' => 'En stock', 'level' => 'en_stock'],
            StockLevel::of(true, 3, true)->toArray(),
        );
        $this->assertSame(
            ['tracked' => true, 'available' => 0, 'inStock' => false, 'label' => 'Rupture de stock', 'level' => 'rupture'],
            StockLevel::of(true, 0, false)->toArray(),
        );
    }
}
