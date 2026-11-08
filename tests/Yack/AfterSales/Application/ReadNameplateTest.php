<?php

declare(strict_types=1);

namespace App\Tests\Yack\AfterSales\Application;

use App\Yack\AfterSales\Application\ReadNameplate;
use App\Yack\AfterSales\Domain\NameplatePhoto;
use App\Yack\AfterSales\Domain\NameplatePhotoRefused;
use App\Yack\AfterSales\Domain\NameplateReader;
use App\Yack\AfterSales\Domain\NameplateReading;
use App\Yack\AfterSales\Infrastructure\Nameplate\FakeNameplateReader;
use App\Yack\Common\Domain\AiUnavailable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Lecteur écrit à la main (il retient les photos reçues) et adapter de démonstration sur les plaques FICTIF :
 * tourne sans vendor/.
 *
 * @internal
 */
#[CoversClass(ReadNameplate::class)]
final class ReadNameplateTest extends TestCase
{
    /** @var list<NameplatePhoto> */
    private array $photos = [];

    #[\Override]
    protected function setUp(): void
    {
        $this->photos = [];
    }

    public function testADemoPlateIsReadWithoutAnyLookup(): void
    {
        $reading = (new ReadNameplate(new FakeNameplateReader()))->read(self::demoPlate('SRK25ZS-W'));

        $this->assertSame('MH21ZSW0004242', $reading->serialNumber);
        $this->assertSame('SRK25ZS-W', $reading->reference);
        $this->assertSame('Mural 2,5 kW R32', $reading->model);
        $this->assertSame('MHI', $reading->brand);
        $this->assertTrue($reading->simulated);
    }

    public function testTheMediaTypeIsReadOnTheContent(): void
    {
        $this->reading(self::demoPlate('illisible'));
        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagejpeg($image);
        $this->reading((string) ob_get_clean());

        $this->assertCount(2, $this->photos);
        $this->assertSame('image/png', $this->photos[0]->mediaType);
        $this->assertSame('image/jpeg', $this->photos[1]->mediaType);
    }

    public function testAPdfIsRefusedWhateverItsName(): void
    {
        $this->expectException(NameplatePhotoRefused::class);
        $this->expectExceptionMessage('Format non accepté (JPEG, PNG ou WebP).');
        $this->reading("%PDF-1.7\n1 0 obj << /Type /Catalog >> endobj\n");
    }

    public function testAnEmptyFileIsRefused(): void
    {
        $this->expectException(NameplatePhotoRefused::class);
        $this->expectExceptionMessage('Le fichier est vide.');
        $this->reading('');
    }

    public function testARefusedPhotoIsNotRead(): void
    {
        try {
            $this->reading('pas une image');
        } catch (NameplatePhotoRefused) {
        }

        $this->assertSame([], $this->photos);
    }

    public function testAnUnavailableReadingGoesUp(): void
    {
        $reader = new class implements NameplateReader {
            public function read(NameplatePhoto $photo): NameplateReading
            {
                throw AiUnavailable::because('plafond atteint');
            }
        };

        $this->expectException(AiUnavailable::class);
        (new ReadNameplate($reader))->read(self::demoPlate('SRK25ZS-W'));
    }

    private function reading(string $bytes): NameplateReading
    {
        $reader = new class($this->photos) implements NameplateReader {
            /** @param list<NameplatePhoto> $photos */
            public function __construct(private array &$photos)
            {
            }

            public function read(NameplatePhoto $photo): NameplateReading
            {
                $this->photos[] = $photo;

                return NameplateReading::unreadable(simulated: true);
            }
        };

        return (new ReadNameplate($reader))->read($bytes);
    }

    private static function demoPlate(string $name): string
    {
        $file = \dirname(__DIR__, 4).'/public/yack/demo/plaques/plaque-'.$name.'-FICTIF.png';
        self::assertTrue(is_file($file), $file);

        return (string) file_get_contents($file);
    }
}
