<?php

declare(strict_types=1);

namespace App\Tests\Yack\Steering\Domain;

use App\Yack\Steering\Domain\Variation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Variation::class)]
final class VariationTest extends TestCase
{
    /** @return \Iterator<string, array{int, int, string}> */
    public static function changes(): \Iterator
    {
        yield 'hausse' => [11_200, 10_000, "+12\u{00A0}%"];
        yield 'baisse décimale' => [57_465, 60_078, "-4,3\u{00A0}%"];
        yield 'stable' => [500, 500, "0\u{00A0}%"];
        yield 'doublé' => [2_000, 1_000, "+100\u{00A0}%"];
    }

    #[DataProvider('changes')]
    public function testThePercentOfThePreviousPeriod(int $current, int $previous, string $label): void
    {
        $this->assertSame($label, Variation::between($current, $previous)?->label());
    }

    public function testNoVariationWithoutAPreviousPeriod(): void
    {
        $this->assertNull(Variation::between(1_000, 0));
    }

    public function testDirection(): void
    {
        $this->assertTrue(Variation::between(2, 1)?->isUp());
        $this->assertTrue(Variation::between(1, 2)?->isDown());
        $this->assertFalse(Variation::between(1, 1)?->isUp());
    }
}
