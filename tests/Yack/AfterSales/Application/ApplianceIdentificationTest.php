<?php

declare(strict_types=1);

namespace App\Tests\Yack\AfterSales\Application;

use App\Yack\AfterSales\Application\ApplianceIdentification;
use App\Yack\AfterSales\Application\IdentificationStatus;
use App\Yack\AfterSales\Domain\NameplateReading;
use App\Yack\Catalog\Domain\ProductReference;
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
        $result = ApplianceIdentification::unreadable(NameplateReading::unreadable(simulated: false, model: 'Mural 2,5 kW'));

        $this->assertSame(IdentificationStatus::Unreadable, $result->status);
        $this->assertNull($result->serialNumber);
        $this->assertNull($result->reference);
        $this->assertNull($result->product);
        $this->assertSame('Mural 2,5 kW', $result->model);
        $this->assertFalse($result->simulated);
    }

    public function testProductCarriesTheCatalogProductAndWhatWasRead(): void
    {
        $product = new ProductReference('SRK25ZS-W', 'SRK25ZS-W Mural - 2,5kW - R32', 'srk25zs-w-mural', 'MHI', ['Notice diagnostic']);

        $result = ApplianceIdentification::product(NameplateReading::read('MH21ZSW0004242', 'Mural 2,5 kW', simulated: true, reference: 'SRK25ZS-W'), $product);

        $this->assertSame(IdentificationStatus::Product, $result->status);
        $this->assertSame($product, $result->product);
        $this->assertSame('MH21ZSW0004242', $result->serialNumber);
        $this->assertSame('SRK25ZS-W', $result->reference);
        $this->assertTrue($result->simulated);
        $this->assertSame('Modèle reconnu dans le catalogue', $result->message());
    }

    public function testUnavailableIsNeverSimulated(): void
    {
        $result = ApplianceIdentification::unavailable();

        $this->assertSame('unavailable', $result->status->value);
        $this->assertFalse($result->simulated);
        $this->assertNull($result->serialNumber);
    }

    public function testFoundNeedsASerialNumber(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ApplianceIdentification::found(NameplateReading::read(null, null, simulated: true, reference: 'SRK25ZS-W'));
    }

    public function testAnUnreadableReadingCannotBeUnknown(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ApplianceIdentification::unknown(NameplateReading::unreadable(simulated: true));
    }

    public function testStatusValuesOfTheApi(): void
    {
        $this->assertSame(['found', 'product', 'unreadable', 'unknown', 'unavailable'], array_map(
            static fn (IdentificationStatus $status): string => $status->value,
            IdentificationStatus::cases(),
        ));
    }
}
