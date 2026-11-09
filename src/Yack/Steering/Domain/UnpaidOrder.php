<?php

declare(strict_types=1);

namespace App\Yack\Steering\Domain;

/**
 * A placed order (not cancelled) whose payment is still awaited, with the code of its payment method.
 */
final readonly class UnpaidOrder
{
    public function __construct(
        public int $orderId,
        public string $orderNumber,
        public int $customerId,
        public string $customerLabel,
        public int $total,
        public \DateTimeImmutable $orderedAt,
        public string $paymentMethodCode,
    ) {
    }
}
