<?php

declare(strict_types=1);

namespace App\Tests\Yack\AfterSales\Infrastructure\Nameplate;

use App\Yack\AfterSales\Domain\NameplatePhoto;
use App\Yack\AfterSales\Infrastructure\Nameplate\FakeNameplateReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FakeNameplateReader::class)]
final class FakeNameplateReaderTest extends TestCase
{
    private FakeNameplateReader $reader;

    #[\Override]
    protected function setUp(): void
    {
        $this->reader = new FakeNameplateReader();
    }

    /** The FICTIF plates of public/yack/demo/plaques/ (bin/yack-demo-plaques.php) are read. */
    #[DataProvider('demoPlates')]
    public function testDemoPlatesAreRead(string $name, string $serial, string $reference, string $model, ?string $brand): void
    {
        $reading = $this->reader->read(self::demoPlate($name));

        $this->assertTrue($reading->isReadable());
        $this->assertSame($serial, $reading->serialNumber);
        $this->assertSame($reference, $reading->reference);
        $this->assertSame($model, $reading->model);
        $this->assertSame($brand, $reading->brand);
        $this->assertTrue($reading->simulated);
    }

    /** @return \Iterator<string, array{string, string, string, string, ?string}> */
    public static function demoPlates(): \Iterator
    {
        yield 'PAC RHOSS' => ['YK25EVO08M001234', 'YK25EVO08M001234', 'RHOSS-AIREAU-R32', 'PAC air/eau RHOSS R32', 'YACK'];
        yield 'Mural Titane' => ['YK19KAZ35S004567', 'YK19KAZ35S004567', 'SRK25ZS-WFT', 'Mural Titane Wifi 2,5 kW', 'YACK'];
        yield 'Hydrolution' => ['YK24HYD11S000321', 'YK24HYD11S000321', 'FDCW-VNX-A', 'Hydrolution', 'YACK'];
        yield 'Ballon 200 L' => ['YK22THD200000789', 'YK22THD200000789', 'YORABTM200', 'Ballon monobloc 200 L', 'YACK'];
        yield 'MHI label, catalog reference' => ['SRK25ZS-W', 'MH21ZSW0004242', 'SRK25ZS-W', 'Mural 2,5 kW R32', 'MHI'];
        yield 'outside the installed base' => ['YK23EVO06M009999', 'YK23EVO06M009999', 'YK-PAE-M06', 'Evolys monobloc 6 kW', 'YACK'];
        yield 'label without a printed brand' => ['sans-marque', 'MH22ZSW0003535', 'SRK35ZS-W', 'Mural 3,5 kW R32', null];
    }

    /** readDemoPlate() tells a FICTIF plate (marker present) from any other photo: the fallback of the selector. */
    public function testReadDemoPlateIsNullWithoutTheMarker(): void
    {
        $demo = $this->reader->readDemoPlate(self::demoPlate('SRK25ZS-W'));
        $this->assertNotNull($demo);
        $this->assertSame('MH21ZSW0004242', $demo->serialNumber);
        $this->assertTrue($demo->simulated);

        $this->assertNull($this->reader->readDemoPlate(self::demoPlate('illisible')));
        $this->assertNull($this->reader->readDemoPlate(NameplatePhoto::of(self::plainPng(), 'image/png')));
        $this->assertNull($this->reader->readDemoPlate(self::pngWithText(FakeNameplateReader::MARKER . "\0pas du json")));
    }

    public function testMarkerWithoutBrandKeyHasNoBrand(): void
    {
        $reading = $this->reader->read(self::png(['serial' => 'YK24HYD11S000321', 'reference' => 'FDCW-VNX-A', 'model' => 'Hydrolution']));

        $this->assertTrue($reading->isReadable());
        $this->assertNull($reading->brand);
    }

    public function testBrandThatIsNotATextIsIgnored(): void
    {
        $this->assertNull($this->reader->read(self::png(['serial' => 'YK24HYD11S000321', 'brand' => ['MHI']]))->brand);
    }

    public function testBlurredDemoPhotoIsUnreadable(): void
    {
        $reading = $this->reader->read(self::demoPlate('illisible'));

        $this->assertFalse($reading->isReadable());
        $this->assertTrue($reading->simulated);
    }

    public function testMarkerIsNormalised(): void
    {
        $reading = $this->reader->read(self::png(['serial' => 'yk24-hyd11s 000321', 'model' => 'Hydrolution']));

        $this->assertSame('YK24HYD11S000321', $reading->serialNumber);
        $this->assertNull($reading->reference);
    }

    public function testMarkerWithAReferenceOnlyIsReadable(): void
    {
        $reading = $this->reader->read(self::png(['serial' => null, 'reference' => 'SRK25ZS-W', 'model' => null]));

        $this->assertTrue($reading->isReadable());
        $this->assertNull($reading->serialNumber);
        $this->assertSame('SRK25ZS-W', $reading->reference);
    }

    public function testWebpIsUnreadable(): void
    {
        $image = imagecreatetruecolor(20, 20);
        ob_start();
        imagewebp($image);

        $this->assertFalse($this->reader->read(NameplatePhoto::of((string) ob_get_clean(), 'image/webp'))->isReadable());
    }

    public function testPngWithoutMarkerIsUnreadable(): void
    {
        $reading = $this->reader->read(NameplatePhoto::of(self::plainPng(), 'image/png'));

        $this->assertFalse($reading->isReadable());
        $this->assertTrue($reading->simulated);
    }

    public function testJpegIsUnreadable(): void
    {
        $image = imagecreatetruecolor(20, 20);
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();

        $this->assertFalse($this->reader->read(NameplatePhoto::of($jpeg, 'image/jpeg'))->isReadable());
    }

    public function testDamagedMarkerIsUnreadable(): void
    {
        $png = self::png(['serial' => 'YK24HYD11S000321', 'model' => 'Hydra'])->bytes;
        $damaged = str_replace('YK24HYD11S000321', 'YK24HYD11S000999', $png); // CRC no longer matches

        $this->assertFalse($this->reader->read(NameplatePhoto::of($damaged, 'image/png'))->isReadable());
    }

    public function testMarkerWithoutSerialIsUnreadable(): void
    {
        $this->assertFalse($this->reader->read(self::png(['model' => 'Hydrolution']))->isReadable());
        $this->assertFalse($this->reader->read(self::png(['serial' => 'PLAQUE', 'model' => 'Hydrolution']))->isReadable());
    }

    public function testMarkerThatIsNotJsonIsUnreadable(): void
    {
        $this->assertFalse($this->reader->read(self::pngWithText(FakeNameplateReader::MARKER."\0pas du json"))->isReadable());
    }

    public function testOtherTextChunksAreIgnored(): void
    {
        $this->assertFalse($this->reader->read(self::pngWithText("Comment\0YK24HYD11S000321"))->isReadable());
    }

    public function testTruncatedPngIsUnreadable(): void
    {
        $png = self::png(['serial' => 'YK24HYD11S000321', 'model' => 'Hydra'])->bytes;

        $this->assertFalse($this->reader->read(NameplatePhoto::of(substr($png, 0, 40), 'image/png'))->isReadable());
    }

    public function testFileClaimedPngButNotPngIsUnreadable(): void
    {
        $this->assertFalse($this->reader->read(NameplatePhoto::of('not a png', 'image/png'))->isReadable());
    }

    private static function demoPlate(string $name): NameplatePhoto
    {
        $file = \dirname(__DIR__, 5).'/public/yack/demo/plaques/plaque-'.$name.'-FICTIF.png';
        self::assertTrue(is_file($file), $file);

        return NameplatePhoto::of((string) file_get_contents($file), 'image/png');
    }

    /** @param array<string, mixed> $printed */
    private static function png(array $printed): NameplatePhoto
    {
        return self::pngWithText(FakeNameplateReader::MARKER."\0".json_encode($printed, \JSON_THROW_ON_ERROR));
    }

    /** A small PNG with a tEXt chunk right after IHDR, as written by bin/yack-demo-plaques.php. */
    private static function pngWithText(string $data): NameplatePhoto
    {
        $png = self::plainPng();
        $chunk = pack('N', \strlen($data)).'tEXt'.$data.pack('N', crc32('tEXt'.$data));

        return NameplatePhoto::of(substr($png, 0, 33).$chunk.substr($png, 33), 'image/png');
    }

    private static function plainPng(): string
    {
        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
