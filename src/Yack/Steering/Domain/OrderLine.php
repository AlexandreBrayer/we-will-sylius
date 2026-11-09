<?php

declare(strict_types=1);

namespace App\Yack\Steering\Domain;

/**
 * A line of a placed order: amount excl. tax after discounts and escompte (line total minus its VAT), escompte
 * granted on the line (positive), main taxon of the product and payment method of the order.
 */
final readonly class OrderLine
{
    public function __construct(
        public int $orderId,
        public \DateTimeImmutable $orderedAt,
        public ?string $paymentMethodCode,
        public ?string $paymentMethodName,
        public ?int $taxonId,
        public int $amount,
        public int $escompte,
    ) {
    }
}
