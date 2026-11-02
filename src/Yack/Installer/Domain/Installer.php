<?php

declare(strict_types=1);

namespace App\Yack\Installer\Domain;

final readonly class Installer
{
    /**
     * @param list<string>   $ranges     product ranges the installer is certified for
     * @param list<Activity> $activities
     */
    public function __construct(
        public string $name,
        public string $city,
        public string $postcode,
        public string $sector,
        public array $ranges,
        public array $activities,
        public float $latitude,
        public float $longitude,
        public ?string $phone = null,
        public bool $certifiedRge = false,
    ) {
    }

    public function matches(InstallerCriteria $criteria): bool
    {
        return (null === $criteria->sector || $criteria->sector === $this->sector) &&
            (null === $criteria->range || in_array($criteria->range, $this->ranges, true)) &&
            (null === $criteria->activity || in_array($criteria->activity, $this->activities, true));
    }
}
