<?php

declare(strict_types=1);

namespace App\Yack\Payment\Application\Card;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;

/**
 * Payment still to be made on a placed order: the order is neither a cart nor cancelled, its
 * payment state is "awaiting payment" and Sylius keeps a payment in state "new" for it (a
 * refused or cancelled card attempt leaves a new one behind).
 */
final class PayableOrder
{
    public static function pendingPayment(OrderInterface $order): ?PaymentInterface
    {
        if (OrderInterface::STATE_NEW !== $order->getState() || OrderPaymentStates::STATE_AWAITING_PAYMENT !== $order->getPaymentState()) {
            return null;
        }

        return $order->getLastPayment(PaymentInterface::STATE_NEW);
    }
}
