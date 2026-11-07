<?php

declare(strict_types=1);

namespace App\Tests\Yack\AfterSales\Application;

use App\Yack\AfterSales\Application\ApplianceIdentification;
use App\Yack\AfterSales\Application\IdentificationStatus;
use App\Yack\AfterSales\Domain\NameplateReading;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ApplianceIdentification::class)]
final class ApplianceIdentificationTest extends TestCase
{
    public function testUnreadableKeepsTheModelButNoSerial(): void
    {
        $result = ApplianceIdentification::unreadable(NameplateReading::unreadable(simulated: false, model: 'Kaze 3,5 kW'));

        $this->assertSame(IdentificationStatus::Unreadable, $result->status);
        $this->assertNull($result->serialNumber);
        $this->assertSame('Kaze 3,5 kW', $result->model);
        $this->assertFalse($result->simulated);
    }

    public function testUnavailableIsNeverSimulated(): void
    {
        $result = ApplianceIdentification::unavailable();

        $this->assertSame('unavailable', $result->status->value);
        $this->assertFalse($result->simulated);
        $this->assertNull($result->serialNumber);
    }

    public function testAnUnreadableReadingCannotBeFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ApplianceIdentification::found(NameplateReading::unreadable(simulated: true));
    }

    public function testAnUnreadableReadingCannotBeUnknown(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ApplianceIdentification::unknown(NameplateReading::unreadable(simulated: true));
    }

    public function testStatusValuesOfTheApi(): void
    {
        $this->assertSame(['found', 'unreadable', 'unknown', 'unavailable'], array_map(
            static fn (IdentificationStatus $status): string => $status->value,
            IdentificationStatus::cases(),
        ));
    }
}
