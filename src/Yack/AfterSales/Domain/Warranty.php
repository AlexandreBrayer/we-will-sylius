<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Domain;

/**
 * Warranty coverage of an installed equipment, as known by Yack.
 */
final readonly class Warranty
{
    public function __construct(
        public string $serialNumber,
        public \DateTimeImmutable $commissionedAt,
        public \DateTimeImmutable $partsCoverageEnd,
        public \DateTimeImmutable $compressorCoverageEnd,
        public string $source,
    ) {
        if ($partsCoverageEnd < $commissionedAt || $compressorCoverageEnd < $commissionedAt) {
            throw new \InvalidArgumentException('Warranty cannot end before commissioning.');
        }
    }

    public function isPartsCoveredAt(\DateTimeImmutable $date): bool
    {
        return $date <= $this->partsCoverageEnd;
    }

    public function isCompressorCoveredAt(\DateTimeImmutable $date): bool
    {
        return $date <= $this->compressorCoverageEnd;
    }
}
