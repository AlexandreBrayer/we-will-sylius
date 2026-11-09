<?php

declare(strict_types=1);

namespace App\Yack\Steering\Domain;

/**
 * Unpaid échéances of one customer: total, part overdue, oldest échéance and its days late.
 */
final readonly class CustomerBalance
{
    /** @param list<Receivable> $receivables oldest échéance first */
    private function __construct(
        public int $customerId,
        public string $customerLabel,
        public int $total,
        public int $overdue,
        public \DateTimeImmutable $oldestDueAt,
        public int $daysLate,
        public array $receivables,
    ) {
    }

    /**
     * One balance per customer, the latest first (most days late, then highest amount).
     *
     * @param list<Receivable> $receivables
     *
     * @return list<self>
     */
    public static function byCustomer(array $receivables, \DateTimeImmutable $now): array
    {
        $groups = [];
        foreach ($receivables as $receivable) {
            $groups[$receivable->customerId][] = $receivable;
        }

        $balances = [];
        foreach ($groups as $customerId => $group) {
            usort($group, static fn (Receivable $a, Receivable $b): int => [$a->dueAt, $a->orderNumber] <=> [$b->dueAt, $b->orderNumber]);
            $oldest = $group[0];
            $balances[] = new self(
                $customerId,
                $oldest->customerLabel,
                array_sum(array_map(static fn (Receivable $r): int => $r->amount, $group)),
                array_sum(array_map(static fn (Receivable $r): int => $r->isLateAt($now) ? $r->amount : 0, $group)),
                $oldest->dueAt,
                $oldest->daysLateAt($now),
                $group,
            );
        }

        usort($balances, static fn (self $a, self $b): int => [$b->daysLate, $b->total, $a->customerLabel] <=> [$a->daysLate, $a->total, $b->customerLabel]);

        return $balances;
    }

    public function isLate(): bool
    {
        return $this->daysLate > 0;
    }
}
