<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Fixture;

/**
 * One demo order decided by DemoOrderPlanner: who, when, what, how it is paid and delivered, and
 * what happened to it (null date = not happened yet). Cancelled: never paid nor shipped.
 * Refunded: paid, refunded before shipping, then cancelled.
 */
final readonly class PlannedOrder
{
    /** @param array<string, int> $lines quantity by variant code */
    public function __construct(
        public string $customerEmail,
        public \DateTimeImmutable $placedAt,
        public array $lines,
        public string $paymentMethod,
        public string $shippingMethod,
        public ?\DateTimeImmutable $paidAt,
        public ?\DateTimeImmutable $shippedAt,
        public ?string $tracking,
        public ?\DateTimeImmutable $cancelledAt,
        public ?\DateTimeImmutable $refundedAt,
        public ?string $notes,
    ) {
    }

    /** Date of the last event of the order (its "updated at"). */
    public function lastEventAt(): \DateTimeImmutable
    {
        $dates = array_filter([$this->placedAt, $this->paidAt, $this->shippedAt, $this->cancelledAt, $this->refundedAt]);

        return max($dates);
    }
}
