<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Sylius;

use App\OrderProcessing\EscompteProcessor;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Placed order whose payment method changed (card refused → bank transfer): escompte and VAT
 * recomputed for the new method, on the lines, promotions and shipping as ordered; the payment
 * to make takes the new total.
 *
 * Sylius' order processors only touch carts (`canBeProcessed()`): the order is seen as a cart
 * for these two processors only, then gets its state back.
 */
final readonly class PlacedOrderRecalculator
{
    public function __construct(
        #[Autowire(service: EscompteProcessor::class)]
        private OrderProcessorInterface $escompteProcessor,
        #[Autowire(service: 'sylius.order_processing.order_taxes_processor')]
        private OrderProcessorInterface $taxesProcessor,
    ) {
    }

    public function recalculate(OrderInterface $order): void
    {
        $state = $order->getState();
        $order->setState(OrderInterface::STATE_CART);
        try {
            // The escompte base is the unit total before VAT, as in the cart (taxes cleared first).
            foreach ($order->getItems() as $item) {
                $item->removeAdjustmentsRecursively(AdjustmentInterface::TAX_ADJUSTMENT);
            }
            $this->escompteProcessor->process($order);
            $this->taxesProcessor->process($order);
        } finally {
            $order->setState($state);
        }

        foreach ($order->getPayments() as $payment) {
            if (PaymentInterface::STATE_NEW === $payment->getState()) {
                $payment->setAmount($order->getTotal());
            }
        }
    }
}
