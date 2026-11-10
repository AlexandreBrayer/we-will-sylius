<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Application;

use App\Entity\Catalog\SyncRun;
use App\Entity\Catalog\SyncRunLine;
use App\Yack\Catalog\Domain\Import\ImportColumn;
use App\Yack\Catalog\Domain\Import\ImportedLine;
use App\Yack\Catalog\Domain\InvalidSourceProduct;
use App\Yack\Catalog\Domain\ProductFeed;
use App\Yack\Catalog\Domain\ProductRecordMapper;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SyncContext;
use App\Yack\Catalog\Domain\SyncOutcome;
use App\Yack\Catalog\Domain\SyncReport;
use App\Yack\Catalog\Domain\SyncRuns;
use App\Yack\Catalog\Domain\SyncSource;
use App\Yack\Catalog\Domain\SyncTrigger;

/**
 * Applies again the records a run rejected, once corrected from the back-office (the source fixed, or
 * the faulty cells typed again on the detail of the run): each record kept as received goes through the
 * same mapping and the same use case as the API feed, in a new run linked to the first one.
 */
final readonly class ReplayRejectedRecords
{
    /** Columns an administrator may correct before a replay. */
    public const EDITABLE = [ImportColumn::Name, ImportColumn::Price, ImportColumn::Stock, ImportColumn::Active, ImportColumn::Category, ImportColumn::Brand];

    public function __construct(
        private SyncRuns $runs,
        private ProductRecordMapper $mapper,
        private ApplyProductFeed $apply,
    ) {
    }

    /**
     * Rejected lines that can be applied again: those whose record was kept, and not yet applied by a
     * replay of the run.
     *
     * @return list<SyncRunLine>
     */
    public function replayable(SyncRun $run): array
    {
        if ($run->isDryRun()) {
            return [];
        }

        $settled = [];
        foreach ($this->runs->replaysOf($run) as $replay) {
            foreach ($replay->getLines() as $line) {
                if (SyncOutcome::Failed !== $line->getOutcome()) {
                    $settled[$line->getCode()] = true;
                }
            }
        }

        return array_values(array_filter(
            $this->runs->rejections($run),
            static fn (SyncRunLine $line): bool => null !== $line->getPayload() && !isset($settled[$line->getCode()]),
        ));
    }

    /**
     * @param array<int|string, array<string, string>> $corrections id of the line => column (ImportColumn value) => cell typed
     *
     * @throws \LogicException when the run has no record to replay
     */
    public function __invoke(SyncRun $run, array $corrections, ?string $operator): SyncReport
    {
        $lines = $this->replayable($run);
        if ([] === $lines) {
            throw new \LogicException(sprintf('Run %d has no rejected record to replay.', (int) $run->getId()));
        }

        $products = [];
        $rejected = [];
        $payloads = [];
        $numbers = [];
        foreach ($lines as $line) {
            $corrected = ImportedLine::fill($line->getPayload() ?? [], self::cells($corrections[(int) $line->getId()] ?? []));
            $code = $corrected->code() ?? $line->getCode();
            if (!$corrected->isValid()) {
                $rejected[] = new RejectedRecord($code, $corrected->reason(), $corrected->record, $line->getFileLine());

                continue;
            }

            try {
                $products[] = $this->mapper->map($corrected->record);
            } catch (InvalidSourceProduct $exception) {
                $rejected[] = new RejectedRecord($code, $exception->getMessage(), $corrected->record, $line->getFileLine());

                continue;
            }
            $payloads[$code] = $corrected->record;
            if (null !== $line->getFileLine()) {
                $numbers[$code] = $line->getFileLine();
            }
        }

        return ($this->apply)(
            new ProductFeed(sprintf('Rejets de la synchronisation n° %d', (int) $run->getId()), $products, $rejected, $payloads, $numbers),
            new SyncContext(SyncSource::Replay, SyncTrigger::BackOffice, $operator, false, $run->getId()),
        );
    }

    /**
     * Cells typed for the editable columns; the others are ignored.
     *
     * @param array<string, string> $cells
     *
     * @return list<array{ImportColumn, string}>
     */
    private static function cells(array $cells): array
    {
        $pairs = [];
        foreach (self::EDITABLE as $column) {
            if (isset($cells[$column->value])) {
                $pairs[] = [$column, $cells[$column->value]];
            }
        }

        return $pairs;
    }
}
