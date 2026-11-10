<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain;

use App\Yack\Catalog\Domain\InvalidSourceProduct;
use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Domain\SourceProduct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SourceProduct::class)]
final class SourceProductTest extends TestCase
{
    #[DataProvider('images')]
    public function testHasAPhotoOnlyWhenTheImageIsNotTheGenericVisual(?string $image, ?string $fallback, bool $expected): void
    {
        $this->assertSame($expected, self::product($image, $fallback)->hasPhoto());
    }

    /** @return \Iterator<string, array{?string, ?string, bool}> */
    public static function images(): \Iterator
    {
        yield 'photo et visuel de repli' => ['/d/images/reel/SRF50ZSX-W.jpg', '/d/images/console.png', true];
        yield 'visuel de repli en attendant la photo' => ['/d/images/console.png', '/d/images/console.png', false];
        yield 'image sans visuel de repli (pièce détachée)' => ['/d/images/part-fan.png', null, false];
        yield 'aucune image' => [null, null, false];
    }

    #[DataProvider('invalidProducts')]
    public function testRefusesAnInvalidProduct(string $code, string $name, int $price, int $stock, string $message): void
    {
        $this->expectException(InvalidSourceProduct::class);
        $this->expectExceptionMessage($message);

        new SourceProduct($code, $name, true, ProductKind::Finished, 'pac_air_eau', $price, $stock);
    }

    /** @return \Iterator<string, array{string, string, int, int, string}> */
    public static function invalidProducts(): \Iterator
    {
        yield 'code vide' => [' ', 'Produit', 100, 1, 'Code produit manquant.'];
        yield 'nom vide' => ['YK-1', ' ', 100, 1, 'Produit « YK-1 » : nom manquant.'];
        yield 'prix négatif' => ['YK-1', 'Produit', -150, 1, "Produit « YK-1 » : prix négatif (-1,50\u{a0}€)."];
        yield 'stock négatif' => ['YK-1', 'Produit', 100, -2, 'Produit « YK-1 » : stock négatif (-2).'];
    }

    private static function product(?string $image, ?string $fallback): SourceProduct
    {
        return new SourceProduct('SRF50ZSX-W', 'Console', true, ProductKind::Finished, 'pac_air_eau', 99090, 13, imagePath: $image, fallbackImagePath: $fallback);
    }
}
