<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain;

use App\Yack\Catalog\Domain\OnlineSale;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(OnlineSale::class)]
final class OnlineSaleTest extends TestCase
{
    #[DataProvider('provideAttributeValues')]
    public function testIsSoldOnline(bool $expected, mixed $attributeValue): void
    {
        $this->assertSame($expected, OnlineSale::isSoldOnline($attributeValue));
    }

    /**
     * @return \Iterator<string, array{bool, mixed}>
     */
    public static function provideAttributeValues(): \Iterator
    {
        yield 'no attribute (spare part)' => [true, null];
        yield 'checked' => [true, true];
        yield 'checked, stored as 1' => [true, 1];
        yield 'checked, stored as "1"' => [true, '1'];
        yield 'unchecked' => [false, false];
        yield 'unchecked, stored as 0' => [false, 0];
        yield 'unchecked, stored as ""' => [false, ''];
    }
}
