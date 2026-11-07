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
        $reading = NameplateReading::read('yk24-hyd11s 000321', '  Hydra   Split 11 kW ', simulated: true);

        $this->assertTrue($reading->isReadable());
        $this->assertSame('YK24HYD11S000321', $reading->serialNumber);
        $this->assertSame('Hydra Split 11 kW', $reading->model);
        $this->assertTrue($reading->simulated);
    }

    public function testImplausibleSerialMakesTheReadingUnreadable(): void
    {
        $reading = NameplateReading::read('ILLISIBLE', 'Kaze 3,5 kW', simulated: false);

        $this->assertFalse($reading->isReadable());
        $this->assertNull($reading->serialNumber);
        $this->assertSame('Kaze 3,5 kW', $reading->model);
        $this->assertFalse($reading->simulated);
    }

    public function testMissingSerialIsUnreadable(): void
    {
        $this->assertFalse(NameplateReading::read(null, null, simulated: true)->isReadable());
    }

    public function testUnreadableKeepsNoEmptyModel(): void
    {
        $reading = NameplateReading::unreadable(simulated: true, model: '   ');

        $this->assertFalse($reading->isReadable());
        $this->assertNull($reading->model);
        $this->assertTrue($reading->simulated);
    }

    public function testModelIsCut(): void
    {
        $reading = NameplateReading::read('YK24HYD11S000321', str_repeat('é', 200), simulated: false);

        $this->assertSame(NameplateReading::MODEL_MAX, mb_strlen((string) $reading->model));
    }
}
