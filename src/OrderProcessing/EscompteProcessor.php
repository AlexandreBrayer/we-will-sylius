<?php

declare(strict_types=1);

namespace App\OrderProcessing;

use App\Entity\Customer\Customer;
use App\Yack\Payment\Domain\PaymentMethodEscompteRates;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\AdjustmentInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Webmozart\Assert\Assert;

/**
 * Escompte = order-level negative adjustment on the excl. tax total.
 *
 * Rate resolution: the payment method chosen at checkout when it is configured
 * (PaymentMethodEscompteRates, 0 = explicitly no escompte), otherwise the customer's own
 * escompte (rate + delay set in the back-office).
 */
final class EscompteProcessor implements OrderProcessorInterface
{
    public const ESCOMPTE_ADJUSTMENT = 'escompte';

    /** @param AdjustmentFactoryInterface<AdjustmentInterface> $adjustmentFactory */
    public function __construct(
        private AdjustmentFactoryInterface $adjustmentFactory,
        private PaymentMethodEscompteRates $paymentMethodEscompteRates,
    ) {
    }

    public function process(BaseOrderInterface $order): void
    {
        Assert::isInstanceOf($order, OrderInterface::class);

        if (!$order->canBeProcessed()) {
            return;
        }

        $order->removeAdjustments(self::ESCOMPTE_ADJUSTMENT);

        $escompteRate = $this->paymentMethodRate($order) ?? $this->customerRate($order);
        if (null === $escompteRate || $escompteRate <= 0) {
            return;
        }

        // HT = total TTC - taxes
        $htTotal = $order->getTotal() - $order->getTaxTotal();
        if ($htTotal <= 0) {
            return;
        }

        $escompteAmount = -(int) round($htTotal * $escompteRate / 100);

        $adjustment = $this->adjustmentFactory->createWithData(
            self::ESCOMPTE_ADJUSTMENT,
            self::label($escompteRate),
            $escompteAmount,
        );

        $order->addAdjustment($adjustment);
    }

    public static function label(float $escompteRate): string
    {
        $rate = rtrim(rtrim(number_format($escompteRate, 2, ',', ''), '0'), ',');

        return sprintf('Escompte %s %%', $rate);
    }

    /**
     * Only once the customer has actually chosen the payment method: before that step the
     * cart carries Sylius' default method, which must not grant a discount by itself.
     */
    private function paymentMethodRate(OrderInterface $order): ?float
    {
        if (!in_array($order->getCheckoutState(), [OrderCheckoutStates::STATE_PAYMENT_SELECTED, OrderCheckoutStates::STATE_COMPLETED], true)) {
            return null;
        }

        $paymentMethodCode = $order->getLastPayment(PaymentInterface::STATE_CART)?->getMethod()?->getCode();
        if (null === $paymentMethodCode) {
            return null;
        }

        return $this->paymentMethodEscompteRates->forPaymentMethod($paymentMethodCode);
    }

    private function customerRate(OrderInterface $order): ?float
    {
        $customer = $order->getCustomer();
        if (!$customer instanceof Customer) {
            return null;
        }

        $escompteDays = $customer->getEscompteDays();
        if (null !== $escompteDays && $order->getCreatedAt() !== null) {
            $deadline = (new \DateTimeImmutable())->modify("-{$escompteDays} days");
            if ($order->getCreatedAt() < $deadline) {
                return null;
            }
        }

        return $customer->getEscompteRate();
    }
}
