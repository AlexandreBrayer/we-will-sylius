<?php

declare(strict_types=1);

namespace App\Yack\Steering\Domain;

/**
 * A placed order not shipped yet. paidAt: when its payment was completed, null while it is awaited (a bank transfer
 * order ships before it is paid).
 */
final readonly class OrderToShip
{
    public function __construct(
        public int $orderId,
        public string $orderNumber,
        public string $customerLabel,
        public \DateTimeImmutable $orderedAt,
        public ?\DateTimeImmutable $paidAt,
    ) {
    }

    /** Paid more than 24 hours ago and still not shipped. */
    public function isLateAt(\DateTimeImmutable $now): bool
    {
        return null !== $this->paidAt && $this->paidAt < $now->modify('-24 hours');
    }
}
