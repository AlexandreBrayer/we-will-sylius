<?php

declare(strict_types=1);

namespace App\Yack\Payment\Application;

use App\Yack\Payment\Domain\PaymentInstruction;
use App\Yack\Payment\Domain\PaymentTerms;
use Sylius\Component\Core\Model\OrderInterface;

/**
 * Payment instruction of a placed order: RIB + reference + due date for a bank transfer,
 * or the card confirmation. Null while the order has no number (cart) or no known method.
 */
final readonly class OrderPaymentInstruction
{
    public function __construct(private PaymentTerms $paymentTerms)
    {
    }

    public function forOrder(OrderInterface $order): ?PaymentInstruction
    {
        $methodCode = $order->getLastPayment()?->getMethod()?->getCode();
        $number = $order->getNumber();
        if (null === $methodCode || null === $number) {
            return null;
        }

        $orderedAt = $order->getCheckoutCompletedAt() ?? new \DateTimeImmutable();

        return $this->paymentTerms->instructionFor($methodCode, $number, \DateTimeImmutable::createFromInterface($orderedAt));
    }
}
