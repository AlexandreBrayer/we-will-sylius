<?php

declare(strict_types=1);

namespace App\Yack\Steering\Domain;

/**
 * An order paid by bank transfer and not paid yet: what the customer owes (total incl. tax) and when (échéance =
 * order date + the days of its payment method, app.yack.payment_terms.methods).
 */
final readonly class Receivable
{
    public function __construct(
        public int $orderId,
        public string $orderNumber,
        public int $customerId,
        public string $customerLabel,
        public int $amount,
        public \DateTimeImmutable $orderedAt,
        public \DateTimeImmutable $dueAt,
    ) {
    }

    public static function fromOrder(UnpaidOrder $order, int $dueDays): self
    {
        if ($dueDays <= 0) {
            throw new \InvalidArgumentException('A bank transfer is due a positive number of days after the order.');
        }

        return new self(
            $order->orderId,
            $order->orderNumber,
            $order->customerId,
            $order->customerLabel,
            $order->total,
            $order->orderedAt,
            $order->orderedAt->setTime(0, 0)->modify(sprintf('+%d days', $dueDays)),
        );
    }

    /** Whole days since the échéance, 0 on the day itself and before: an échéance is late from the next day on. */
    public function daysLateAt(\DateTimeImmutable $now): int
    {
        $today = $now->setTime(0, 0);
        $due = $this->dueAt->setTime(0, 0);

        return $today > $due ? (int) $due->diff($today)->days : 0;
    }

    public function isLateAt(\DateTimeImmutable $now): bool
    {
        return $this->daysLateAt($now) > 0;
    }
}
