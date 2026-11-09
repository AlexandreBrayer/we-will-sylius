<?php

declare(strict_types=1);

namespace App\OrderProcessing;

use App\Entity\Customer\Customer;
use App\Yack\Payment\Domain\PaymentMethodEscompteRates;
use Sylius\Component\Core\Distributor\ProportionalIntegerDistributorInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\AdjustmentInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Webmozart\Assert\Assert;

/**
 * Escompte = early-payment discount on the excl. tax amount of the goods (shipping excluded).
 *
 * The amount (rate × items excl. tax, after promotions) is distributed proportionally over
 * the order item units as adjustments of type "escompte". The processor runs before
 * Sylius' OrderTaxesProcessor (priority 15 < 20 promotions, > 10 taxes), so VAT is computed
 * natively on the discounted base: Evolys 8 kW 7 290 € HT − 145,80 € = 7 144,20 € HT,
 * TVA 1 428,84 €, + transport 19,90 € = 8 592,94 € TTC.
 *
 * Rate resolution: the payment method of the order (configured in PaymentMethodEscompteRates,
 * 0 = explicitly no escompte), otherwise the customer's own escompte (rate + delay set in the
 * back-office).
 */
final class EscompteProcessor implements OrderProcessorInterface
{
    public const ESCOMPTE_ADJUSTMENT = 'escompte';

    /**
     * From the payment step on, the cart's payment carries the preselected method: the
     * escompte is shown on first arrival, before the customer confirms the choice.
     */
    private const PAYMENT_STEP_STATES = [
        OrderCheckoutStates::STATE_SHIPPING_SELECTED,
        OrderCheckoutStates::STATE_SHIPPING_SKIPPED,
        OrderCheckoutStates::STATE_PAYMENT_SELECTED,
        OrderCheckoutStates::STATE_COMPLETED,
    ];

    /** @param AdjustmentFactoryInterface<AdjustmentInterface> $adjustmentFactory */
    public function __construct(
        private AdjustmentFactoryInterface $adjustmentFactory,
        private PaymentMethodEscompteRates $paymentMethodEscompteRates,
        private ProportionalIntegerDistributorInterface $distributor,
    ) {
    }

    public function process(BaseOrderInterface $order): void
    {
        Assert::isInstanceOf($order, OrderInterface::class);

        if (!$order->canBeProcessed()) {
            return;
        }

        $order->removeAdjustmentsRecursively(self::ESCOMPTE_ADJUSTMENT);

        $escompteRate = $this->paymentMethodRate($order) ?? $this->customerRate($order);
        if (null === $escompteRate || $escompteRate <= 0) {
            return;
        }

        /** @var list<OrderItemUnitInterface> $units */
        $units = [];
        /** @var list<int> $unitBases */
        $unitBases = [];
        foreach ($order->getItems() as $item) {
            foreach ($item->getUnits() as $unit) {
                Assert::isInstanceOf($unit, OrderItemUnitInterface::class);
                $units[] = $unit;
                // Unit price + promotions; tax adjustments are cleared at this point (priority 60).
                $unitBases[] = max(0, $unit->getTotal());
            }
        }

        $base = array_sum($unitBases);
        if ($base <= 0) {
            return;
        }

        $escompteAmount = (int) round($base * $escompteRate / 100);
        if (0 === $escompteAmount) {
            return;
        }

        $label = self::label($escompteRate);
        foreach ($this->distributor->distribute($unitBases, -$escompteAmount) as $index => $unitAmount) {
            if (0 === $unitAmount) {
                continue;
            }

            $units[$index]->addAdjustment(
                $this->adjustmentFactory->createWithData(self::ESCOMPTE_ADJUSTMENT, $label, $unitAmount),
            );
        }
    }

    public static function label(float $escompteRate): string
    {
        $rate = rtrim(rtrim(number_format($escompteRate, 2, ',', ''), '0'), ',');

        return sprintf('Escompte %s %%', $rate);
    }

    private function paymentMethodRate(OrderInterface $order): ?float
    {
        if (!in_array($order->getCheckoutState(), self::PAYMENT_STEP_STATES, true)) {
            return null;
        }

        // Cart: payment in state "cart"; placed order whose method changed (Payment\Infrastructure\Sylius\PlacedOrderRecalculator): "new".
        $payment = $order->getLastPayment(PaymentInterface::STATE_CART) ?? $order->getLastPayment(PaymentInterface::STATE_NEW);
        $paymentMethodCode = $payment?->getMethod()?->getCode();
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
