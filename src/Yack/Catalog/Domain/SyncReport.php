<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

final class SyncReport
{
    /** @var array<string, int> */
    private array $counters = [];

    /** @var list<RejectedRecord> */
    private array $failures = [];

    public function __construct(public readonly string $origin)
    {
        foreach (SyncOutcome::cases() as $outcome) {
            $this->counters[$outcome->value] = 0;
        }
    }

    public function record(SyncOutcome $outcome): void
    {
        ++$this->counters[$outcome->value];
    }

    public function fail(RejectedRecord $failure): void
    {
        $this->record(SyncOutcome::Failed);
        $this->failures[] = $failure;
    }

    public function count(SyncOutcome $outcome): int
    {
        return $this->counters[$outcome->value];
    }

    /** @return list<RejectedRecord> */
    public function failures(): array
    {
        return $this->failures;
    }

    public function hasFailures(): bool
    {
        return [] !== $this->failures;
    }

    /** @return array<string, int> */
    public function toArray(): array
    {
        return $this->counters;
    }
}
