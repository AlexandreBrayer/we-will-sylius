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
    public function testDemoPlatesAreRead(string $serial, string $model): void
    {
        $file = \dirname(__DIR__, 5).'/public/yack/demo/plaques/plaque-'.$serial.'-FICTIF.png';
        $this->assertTrue(is_file($file), $file);

        $reading = $this->reader->read(NameplatePhoto::of((string) file_get_contents($file), 'image/png'));

        $this->assertTrue($reading->isReadable());
        $this->assertSame($serial, $reading->serialNumber);
        $this->assertSame($model, $reading->model);
        $this->assertTrue($reading->simulated);
    }

    /** @return \Iterator<string, array{string, string}> */
    public static function demoPlates(): \Iterator
    {
        yield 'Evolys 8 kW' => ['YK25EVO08M001234', 'Evolys monobloc 8 kW'];
        yield 'Kaze 3,5 kW' => ['YK19KAZ35S004567', 'Kaze 3,5 kW'];
        yield 'Hydra Split 11 kW' => ['YK24HYD11S000321', 'Hydra Split 11 kW'];
        yield 'Aquaflow 200 L' => ['YK22THD200000789', 'Aquaflow 200 L'];
        yield 'outside the installed base' => ['YK23EVO06M009999', 'Evolys monobloc 6 kW'];
    }

    public function testMarkerIsNormalised(): void
    {
        $reading = $this->reader->read(self::png(['serial' => 'yk24-hyd11s 000321', 'model' => 'Hydra Split 11 kW']));

        $this->assertSame('YK24HYD11S000321', $reading->serialNumber);
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
        $this->assertFalse($this->reader->read(self::png(['model' => 'Hydra']))->isReadable());
        $this->assertFalse($this->reader->read(self::png(['serial' => 'PLAQUE', 'model' => 'Hydra']))->isReadable());
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

    /** @param array<string, string> $printed */
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
