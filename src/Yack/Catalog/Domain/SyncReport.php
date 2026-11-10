<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * What a synchronisation did with each record of a batch (or would do, in a simulation): the change of
 * every product read, the records rejected, the counters by outcome; or why the batch could not be read.
 */
final class SyncReport
{
    /** @var array<string, int> */
    private array $counters = [];

    /** @var list<ProductChange> */
    private array $changes = [];

    /** @var list<RejectedRecord> */
    private array $failures = [];

    private ?int $runId = null;

    private ?string $error = null;

    public function __construct(public readonly string $origin, public readonly bool $dryRun = false)
    {
        foreach (SyncOutcome::cases() as $outcome) {
            $this->counters[$outcome->value] = 0;
        }
    }

    public function record(ProductChange $change): void
    {
        ++$this->counters[$change->outcome->value];
        $this->changes[] = $change;
    }

    public function fail(RejectedRecord $failure): void
    {
        ++$this->counters[SyncOutcome::Failed->value];
        $this->failures[] = $failure;
    }

    /** The whole batch could not be read (source unavailable): nothing was applied. */
    public function sourceFailed(string $error): void
    {
        $this->error = $error;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    public function count(SyncOutcome $outcome): int
    {
        return $this->counters[$outcome->value];
    }

    /** Records read: applied, unchanged and rejected. */
    public function total(): int
    {
        return array_sum($this->counters);
    }

    /** @return list<ProductChange> */
    public function changes(): array
    {
        return $this->changes;
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

    public function status(): SyncStatus
    {
        if (null !== $this->error) {
            return SyncStatus::Failure;
        }

        return $this->hasFailures() ? SyncStatus::Partial : SyncStatus::Success;
    }

    /** The run recorded for this report, once the journal has it. */
    public function recordedAs(int $runId): void
    {
        $this->runId = $runId;
    }

    public function runId(): ?int
    {
        return $this->runId;
    }

    /** @return array<string, int> */
    public function toArray(): array
    {
        return $this->counters;
    }
}
