<?php

declare(strict_types=1);

namespace App\Entity\Catalog;

use App\Yack\Catalog\Domain\SyncContext;
use App\Yack\Catalog\Domain\SyncOutcome;
use App\Yack\Catalog\Domain\SyncReport;
use App\Yack\Catalog\Domain\SyncSource;
use App\Yack\Catalog\Domain\SyncStatus;
use App\Yack\Catalog\Domain\SyncTrigger;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * One synchronisation of the catalog (journal of Catalog\Domain\SyncRuns): API batch, manual import or
 * replay of rejected records, real or simulated (`dryRun`), its counters by outcome and one line per
 * record read (SyncRunLine: field-level changes, or the reason of the rejection and the record received).
 * Written once, at the end of the run; only the address of the alert is added afterwards.
 */
#[ORM\Entity]
#[ORM\Table(name: 'yack_sync_run')]
#[ORM\Index(name: 'idx_yack_sync_run_started_at', columns: ['started_at'])]
class SyncRun
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 16, enumType: SyncSource::class)]
    private SyncSource $source;

    #[ORM\Column(name: 'trigger_kind', type: 'string', length: 16, enumType: SyncTrigger::class)]
    private SyncTrigger $trigger;

    /** Administrator who started the run, null from the command line. */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $operator;

    #[ORM\Column(name: 'dry_run', type: 'boolean')]
    private bool $dryRun;

    /** Run whose rejected records this one applies again. */
    #[ORM\Column(name: 'replay_of', type: 'integer', nullable: true)]
    private ?int $replayOf;

    /** Batch read: feed and export date, or name of the imported file. */
    #[ORM\Column(type: 'string', length: 255)]
    private string $origin;

    #[ORM\Column(name: 'started_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(name: 'finished_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $finishedAt;

    #[ORM\Column(name: 'duration_ms', type: 'integer')]
    private int $durationMs;

    #[ORM\Column(type: 'string', length: 16, enumType: SyncStatus::class)]
    private SyncStatus $status;

    #[ORM\Column(type: 'integer')]
    private int $created = 0;

    #[ORM\Column(type: 'integer')]
    private int $updated = 0;

    #[ORM\Column(type: 'integer')]
    private int $disabled = 0;

    #[ORM\Column(type: 'integer')]
    private int $unchanged = 0;

    #[ORM\Column(type: 'integer')]
    private int $rejected = 0;

    /** Why nothing was read (failure). */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $error = null;

    /** Address alerted of the failure. */
    #[ORM\Column(name: 'notified_to', type: 'string', length: 255, nullable: true)]
    private ?string $notifiedTo = null;

    /** @var Collection<int, SyncRunLine> */
    #[ORM\OneToMany(targetEntity: SyncRunLine::class, mappedBy: 'run', cascade: ['persist'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $lines;

    private function __construct(SyncContext $context, string $origin, \DateTimeImmutable $startedAt, \DateTimeImmutable $finishedAt, SyncStatus $status)
    {
        $this->source = $context->source;
        $this->trigger = $context->trigger;
        $this->operator = null === $context->operator ? null : mb_substr($context->operator, 0, 255);
        $this->dryRun = $context->dryRun;
        $this->replayOf = $context->replayOf;
        $this->origin = mb_substr($origin, 0, 255);
        $this->startedAt = $startedAt;
        $this->finishedAt = $finishedAt;
        $this->durationMs = max(0, (int) round(((float) $finishedAt->format('U.u') - (float) $startedAt->format('U.u')) * 1000));
        $this->status = $status;
        $this->lines = new ArrayCollection();
    }

    /** A run that read its batch: one line per record, rejections after the products. */
    public static function completed(SyncReport $report, SyncContext $context, \DateTimeImmutable $startedAt, \DateTimeImmutable $finishedAt): self
    {
        $run = new self($context, $report->origin, $startedAt, $finishedAt, $report->status());
        $run->created = $report->count(SyncOutcome::Created);
        $run->updated = $report->count(SyncOutcome::Updated);
        $run->disabled = $report->count(SyncOutcome::Disabled);
        $run->unchanged = $report->count(SyncOutcome::Unchanged);
        $run->rejected = $report->count(SyncOutcome::Failed);

        foreach ($report->changes() as $change) {
            $run->lines->add(SyncRunLine::change($run, $run->lines->count() + 1, $change));
        }
        foreach ($report->failures() as $failure) {
            $run->lines->add(SyncRunLine::rejection($run, $run->lines->count() + 1, $failure));
        }

        return $run;
    }

    /** A run that could not read its batch (source unavailable): nothing changed. */
    public static function failed(SyncContext $context, string $origin, string $error, \DateTimeImmutable $startedAt, \DateTimeImmutable $finishedAt): self
    {
        $run = new self($context, $origin, $startedAt, $finishedAt, SyncStatus::Failure);
        $run->error = $error;

        return $run;
    }

    public function notified(string $recipient): void
    {
        $this->notifiedTo = $recipient;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSource(): SyncSource
    {
        return $this->source;
    }

    public function getTrigger(): SyncTrigger
    {
        return $this->trigger;
    }

    public function getOperator(): ?string
    {
        return $this->operator;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    public function getReplayOf(): ?int
    {
        return $this->replayOf;
    }

    public function getOrigin(): string
    {
        return $this->origin;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): \DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    public function getStatus(): SyncStatus
    {
        return $this->status;
    }

    public function count(SyncOutcome $outcome): int
    {
        return match ($outcome) {
            SyncOutcome::Created => $this->created,
            SyncOutcome::Updated => $this->updated,
            SyncOutcome::Disabled => $this->disabled,
            SyncOutcome::Unchanged => $this->unchanged,
            SyncOutcome::Failed => $this->rejected,
        };
    }

    /**
     * Counters by outcome, for the templates (`run.counts.created`).
     *
     * @return array<string, int> outcome value => count
     */
    public function counts(): array
    {
        $counts = [];
        foreach (SyncOutcome::cases() as $outcome) {
            $counts[$outcome->value] = $this->count($outcome);
        }

        return $counts;
    }

    /** Records read: applied, unchanged and rejected. */
    public function total(): int
    {
        return $this->created + $this->updated + $this->disabled + $this->unchanged + $this->rejected;
    }

    /** Products the run wrote (created, updated, disabled); none in a simulation. */
    public function written(): int
    {
        return $this->dryRun ? 0 : $this->created + $this->updated + $this->disabled;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getNotifiedTo(): ?string
    {
        return $this->notifiedTo;
    }

    /** @return list<SyncRunLine> */
    public function getLines(): array
    {
        return array_values($this->lines->toArray());
    }
}
