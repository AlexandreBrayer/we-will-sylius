<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Infrastructure\Warranty;

use App\Yack\AfterSales\Domain\SerialNumberFormat;
use App\Yack\AfterSales\Domain\Warranty;
use App\Yack\AfterSales\Domain\WarrantyProvider;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * POC adapter standing in for the Yack warranty API.
 *
 * Demo serials have hand-picked dates (see docs/POC_YACK.md); any other serial gets
 * deterministic dates derived from its checksum, so the same serial always shows the same warranty.
 * Real implementation: an HTTP adapter calling the Yack API, same interface.
 */
#[AsAlias(WarrantyProvider::class)]
final class FakeWarrantyProvider implements WarrantyProvider
{
    private const SOURCE = 'Simulation API garantie Yack (POC)';

    private const PARTS_COVERAGE = 'P2Y';

    private const COMPRESSOR_COVERAGE = 'P5Y';

    /** serial => commissioning date */
    private const DEMO_SERIALS = [
        'YK25EVO08M001234' => '2025-04-10',
        'YK19KAZ35S004567' => '2019-06-03',
        'YK24HYD11S000321' => '2024-02-21',
        'YK22THD200000789' => '2022-11-08',
    ];

    public function forSerialNumber(string $serialNumber): ?Warranty
    {
        $serial = SerialNumberFormat::normalize($serialNumber);
        if ('' === $serial) {
            return null;
        }

        $commissionedAt = isset(self::DEMO_SERIALS[$serial])
            ? new \DateTimeImmutable(self::DEMO_SERIALS[$serial])
            : (new \DateTimeImmutable('2019-01-01'))->modify(sprintf('+%d days', crc32($serial) % 2190));

        return new Warranty(
            serialNumber: $serial,
            commissionedAt: $commissionedAt,
            partsCoverageEnd: $commissionedAt->add(new \DateInterval(self::PARTS_COVERAGE)),
            compressorCoverageEnd: $commissionedAt->add(new \DateInterval(self::COMPRESSOR_COVERAGE)),
            source: self::SOURCE,
        );
    }
}
