<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\EventListener;

use App\Yack\Catalog\Infrastructure\EventListener\OnlineSaleCartListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(OnlineSaleCartListener::class)]
final class OnlineSaleCartListenerTest extends TestCase
{
    #[DataProvider('provideBodies')]
    public function testVariantCode(?string $expected, string $body): void
    {
        $this->assertSame($expected, OnlineSaleCartListener::variantCode($body));
    }

    /**
     * @return \Iterator<string, array{string|null, string}>
     */
    public static function provideBodies(): \Iterator
    {
        yield 'IRI of the shop API' => ['FDC224KXZRE2', '{"productVariant": "/api/v2/shop/product-variants/FDC224KXZRE2", "quantity": 1}'];
        yield 'encoded code' => ['RC-E5', '{"productVariant": "/api/v2/shop/product-variants/RC%2DE5"}'];
        yield 'bare code' => ['RC-E5', '{"productVariant": "RC-E5"}'];
        yield 'no variant' => [null, '{"quantity": 1}'];
        yield 'empty variant' => [null, '{"productVariant": " "}'];
        yield 'IRI without code' => [null, '{"productVariant": "/api/v2/shop/product-variants/"}'];
        yield 'not a text' => [null, '{"productVariant": 12}'];
        yield 'not JSON' => [null, 'productVariant=RC-E5'];
    }
}
