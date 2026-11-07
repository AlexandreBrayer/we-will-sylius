<?php

declare(strict_types=1);

namespace App\Tests\Yack\AfterSales\Domain;

use App\Yack\AfterSales\Domain\NameplatePhoto;
use App\Yack\AfterSales\Domain\NameplatePhotoRefused;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NameplatePhoto::class)]
#[CoversClass(NameplatePhotoRefused::class)]
final class NameplatePhotoTest extends TestCase
{
    public function testJpegAndPngAreAccepted(): void
    {
        $this->assertSame('image/jpeg', NameplatePhoto::of('jpeg bytes', 'image/jpeg')->mediaType);

        $photo = NameplatePhoto::of('png bytes', 'image/png');
        $this->assertSame('image/png', $photo->mediaType);
        $this->assertSame('png bytes', $photo->bytes);
    }

    public function testEmptyIsRefused(): void
    {
        $this->expectException(NameplatePhotoRefused::class);
        $this->expectExceptionMessage('Le fichier est vide.');
        NameplatePhoto::of('', 'image/png');
    }

    public function testOtherTypesAreRefused(): void
    {
        $this->expectException(NameplatePhotoRefused::class);
        $this->expectExceptionMessage('Format non accepté (JPG, JPEG, PNG).');
        NameplatePhoto::of('%PDF-1.7', 'application/pdf');
    }

    public function testTwentyMegabytesIsTheLimit(): void
    {
        $this->assertSame(20 * 1024 * 1024, NameplatePhoto::MAX_BYTES);
        $this->assertSame(NameplatePhoto::MAX_BYTES, \strlen(NameplatePhoto::of(str_repeat('x', NameplatePhoto::MAX_BYTES), 'image/jpeg')->bytes));

        $this->expectException(NameplatePhotoRefused::class);
        $this->expectExceptionMessage('Fichier trop volumineux (20 Mo maximum)');
        NameplatePhoto::of(str_repeat('x', NameplatePhoto::MAX_BYTES + 1), 'image/jpeg');
    }
}
