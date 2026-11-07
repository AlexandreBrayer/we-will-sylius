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
    public function testLevelAndLabels(
        string $expectedLevel,
        string $expectedLabel,
        string $expectedAvailabilityLabel,
        bool $tracked,
        ?int $available,
        bool $inStock,
    ): void {
        $stock = StockLevel::of($tracked, $available, $inStock);

        $this->assertSame($expectedLevel, $stock->level);
        $this->assertSame($expectedLabel, $stock->label());
        $this->assertSame($expectedAvailabilityLabel, $stock->availabilityLabel());
    }

    /**
     * @return \Iterator<string, array{string, string, string, bool, int|null, bool}>
     */
    public static function provideLevels(): \Iterator
    {
        yield '12 available' => [StockLevel::IN_STOCK, 'En stock', 'En stock', true, 12, true];
        yield 'threshold reached' => [StockLevel::IN_STOCK, 'En stock', 'En stock', true, 10, true];
        yield 'just under the threshold' => [StockLevel::LOW, 'Plus que 9', 'En stock', true, 9, true];
        yield '3 available' => [StockLevel::LOW, 'Plus que 3', 'En stock', true, 3, true];
        yield '0 tracked, not available' => [StockLevel::ON_ORDER, 'Sur commande', 'Sur commande', true, 0, false];
        yield '3 tracked but not available' => [StockLevel::ON_ORDER, 'Sur commande', 'Sur commande', true, 3, false];
        yield 'not tracked, available' => [StockLevel::IN_STOCK, 'En stock', 'En stock', false, null, true];
        yield 'available but quantity 0' => [StockLevel::IN_STOCK, 'En stock', 'En stock', true, 0, true];
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

    public function testNoVariantIsUnavailable(): void
    {
        $stock = StockLevel::noVariant();

        $this->assertSame(StockLevel::UNAVAILABLE, $stock->level);
        $this->assertSame('Indisponible', $stock->label());
        $this->assertSame(
            ['tracked' => false, 'available' => null, 'inStock' => false, 'label' => 'Indisponible', 'level' => 'indisponible'],
            $stock->toArray(),
        );
    }

    public function testWithdrawnKeepsTheQuantityAndChangesTheLabel(): void
    {
        $stock = StockLevel::of(true, 3, true);
        $withdrawn = $stock->withdrawn();

        $this->assertSame('Plus commercialisée', $withdrawn->label());
        $this->assertSame('Plus commercialisée', $withdrawn->availabilityLabel());
        $this->assertSame(
            ['tracked' => true, 'available' => 3, 'inStock' => true, 'label' => 'Plus commercialisée', 'level' => 'indisponible'],
            $withdrawn->toArray(),
        );
        $this->assertSame('Plus que 3', $stock->label());
    }

    public function testToArrayKeepsTheApiContractPlusTheLevel(): void
    {
        $this->assertSame(
            ['tracked' => true, 'available' => 3, 'inStock' => true, 'label' => 'En stock', 'level' => 'faible'],
            StockLevel::of(true, 3, true)->toArray(),
        );
        $this->assertSame(
            ['tracked' => true, 'available' => 12, 'inStock' => true, 'label' => 'En stock', 'level' => 'en_stock'],
            StockLevel::of(true, 12, true)->toArray(),
        );
    }
}
