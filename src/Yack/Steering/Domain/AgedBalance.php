<?php

declare(strict_types=1);

namespace App\Yack\Steering\Domain;

/**
 * Balance âgée de l'échu : the unpaid échéances by days late (1 to 30, 31 to 60, more than 60), plus what is not due
 * yet. Amounts in cents, incl. tax.
 */
final readonly class AgedBalance
{
    private function __construct(
        public int $notDue,
        public int $upTo30,
        public int $from31To60,
        public int $over60,
    ) {
    }

    /** @param list<Receivable> $receivables */
    public static function of(array $receivables, \DateTimeImmutable $now): self
    {
        $buckets = [0, 0, 0, 0];
        foreach ($receivables as $receivable) {
            $days = $receivable->daysLateAt($now);
            $bucket = match (true) {
                0 === $days => 0,
                $days <= 30 => 1,
                $days <= 60 => 2,
                default => 3,
            };
            $buckets[$bucket] += $receivable->amount;
        }

        return new self(...$buckets);
    }

    public function overdue(): int
    {
        return $this->upTo30 + $this->from31To60 + $this->over60;
    }

    public function total(): int
    {
        return $this->notDue + $this->overdue();
    }

    /**
     * Rows of the balance, with the share of each slice in the overdue total (bar width, 0 to 100).
     *
     * @return list<array{label: string, amount: int, share: int}>
     */
    public function rows(): array
    {
        $overdue = $this->overdue();
        $rows = [];
        foreach (['1 à 30 jours' => $this->upTo30, '31 à 60 jours' => $this->from31To60, 'Plus de 60 jours' => $this->over60] as $label => $amount) {
            $rows[] = ['label' => $label, 'amount' => $amount, 'share' => $overdue > 0 ? (int) round($amount * 100 / $overdue) : 0];
        }

        return $rows;
    }
}
