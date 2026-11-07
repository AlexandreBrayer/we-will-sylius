<?php

declare(strict_types=1);

namespace App\Tests\Yack\AfterSales\Domain;

use App\Yack\AfterSales\Domain\NameplateReading;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NameplateReading::class)]
final class NameplateReadingTest extends TestCase
{
    public function testSerialIsNormalised(): void
    {
        $reading = NameplateReading::read('yk24-hyd11s 000321', '  Hydrolution   split ', simulated: true, reference: ' FDCW-VNX-A ');

        $this->assertTrue($reading->isReadable());
        $this->assertSame('YK24HYD11S000321', $reading->serialNumber);
        $this->assertSame('Hydrolution split', $reading->model);
        $this->assertSame('FDCW-VNX-A', $reading->reference);
        $this->assertTrue($reading->simulated);
    }

    public function testImplausibleSerialIsDropped(): void
    {
        $reading = NameplateReading::read('ILLISIBLE', 'Mural 2,5 kW', simulated: false);

        $this->assertFalse($reading->isReadable());
        $this->assertNull($reading->serialNumber);
        $this->assertSame('Mural 2,5 kW', $reading->model);
        $this->assertFalse($reading->simulated);
    }

    public function testAReferenceAloneIsReadable(): void
    {
        $reading = NameplateReading::read(null, null, simulated: false, reference: 'SRK25ZS-W');

        $this->assertTrue($reading->isReadable());
        $this->assertNull($reading->serialNumber);
        $this->assertSame('SRK25ZS-W', $reading->reference);
    }

    public function testReferenceWithoutLetterOrDigitOrTooLongIsDropped(): void
    {
        $this->assertNull(NameplateReading::read(null, null, simulated: true, reference: ' -- ')->reference);
        $this->assertNull(NameplateReading::read(null, null, simulated: true, reference: str_repeat('A', NameplateReading::REFERENCE_MAX + 1))->reference);
        $this->assertSame(NameplateReading::REFERENCE_MAX, mb_strlen((string) NameplateReading::read(null, null, simulated: true, reference: str_repeat('A', NameplateReading::REFERENCE_MAX))->reference));
    }

    public function testMissingSerialAndReferenceIsUnreadable(): void
    {
        $this->assertFalse(NameplateReading::read(null, 'Mural', simulated: true)->isReadable());
    }

    public function testUnreadableKeepsNoEmptyModel(): void
    {
        $reading = NameplateReading::unreadable(simulated: true, model: '   ');

        $this->assertFalse($reading->isReadable());
        $this->assertNull($reading->model);
        $this->assertNull($reading->reference);
        $this->assertTrue($reading->simulated);
    }

    public function testModelIsCut(): void
    {
        $reading = NameplateReading::read('YK24HYD11S000321', str_repeat('é', 200), simulated: false);

        $this->assertSame(NameplateReading::MODEL_MAX, mb_strlen((string) $reading->model));
    }

    public function testProductHintsMostPreciseFirstWithoutRepeat(): void
    {
        $this->assertSame(
            ['SRK25ZS-W', 'Mural 2,5 kW', 'MH21ZSW0004242'],
            NameplateReading::read('MH21ZSW0004242', 'Mural 2,5 kW', simulated: true, reference: 'SRK25ZS-W')->productHints(),
        );
        $this->assertSame(['SRK25ZS-W'], NameplateReading::read(null, 'SRK25ZS-W', simulated: true, reference: 'SRK25ZS-W')->productHints());
        $this->assertSame([], NameplateReading::unreadable(simulated: true)->productHints());
    }
}
