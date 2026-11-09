<?php

declare(strict_types=1);

namespace App\Tests\Yack\AfterSales\Infrastructure\Console;

use App\Yack\AfterSales\Domain\NameplatePhoto;
use App\Yack\AfterSales\Domain\NameplateReader;
use App\Yack\AfterSales\Domain\NameplateReading;
use App\Yack\AfterSales\Infrastructure\Console\RecordingNameplateReader;
use App\Yack\Common\Domain\AiUnavailable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RecordingNameplateReader::class)]
final class RecordingNameplateReaderTest extends TestCase
{
    public function testReadingIsPassedOnAndKept(): void
    {
        $reading = NameplateReading::read('YK24HYD11S000321', 'Hydrolution', simulated: false);
        $inner = $this->createMock(NameplateReader::class);
        $inner->expects($this->once())->method('read')->willReturn($reading);
        $reader = new RecordingNameplateReader($inner);

        $this->assertNull($reader->durationMs());
        $this->assertSame($reading, $reader->read(self::photo()));
        $this->assertSame($reading, $reader->reading());
        $this->assertNull($reader->unavailable());
        $this->assertIsInt($reader->durationMs());
    }

    public function testUnavailableIsKeptAndRethrown(): void
    {
        $unavailable = AiUnavailable::because('trop de demandes');
        $inner = $this->createMock(NameplateReader::class);
        $inner->method('read')->willThrowException($unavailable);
        $reader = new RecordingNameplateReader($inner);

        try {
            $reader->read(self::photo());
            $this->fail('L\'indisponibilité aurait dû remonter au cas d\'usage.');
        } catch (AiUnavailable $caught) {
            $this->assertSame($unavailable, $caught);
        }
        $this->assertSame($unavailable, $reader->unavailable());
        $this->assertNull($reader->reading());
        $this->assertIsInt($reader->durationMs());
    }

    private static function photo(): NameplatePhoto
    {
        return NameplatePhoto::of('octets', 'image/png');
    }
}
