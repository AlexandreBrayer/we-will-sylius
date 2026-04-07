<?php

declare(strict_types=1);

namespace App\OrderProcessing;

use App\Entity\Customer\Customer;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Webmozart\Assert\Assert;

final class EscompteProcessor implements OrderProcessorInterface
{
    public const ESCOMPTE_ADJUSTMENT = 'escompte';

    public function __construct(private AdjustmentFactoryInterface $adjustmentFactory)
    {
    }

    public function process(BaseOrderInterface $order): void
    {
        /** @var OrderInterface $order */
        Assert::isInstanceOf($order, OrderInterface::class);

        $order->removeAdjustments(self::ESCOMPTE_ADJUSTMENT);

        if (!$order->canBeProcessed()) {
            return;
        }

        $customer = $order->getCustomer();
        if (!$customer instanceof Customer) {
            return;
        }

        $escompteRate = $customer->getEscompteRate();
        if (null === $escompteRate || $escompteRate <= 0) {
            return;
        }

        $escompteDays = $customer->getEscompteDays();
        if (null !== $escompteDays && $order->getCreatedAt() !== null) {
            $deadline = (new \DateTimeImmutable())->modify("-{$escompteDays} days");
            if ($order->getCreatedAt() < $deadline) {
                return;
            }
        }

        // HT = total TTC - taxes
        $htTotal = $order->getTotal() - $order->getTaxTotal();
        if ($htTotal <= 0) {
            return;
        }

        $escompteAmount = -(int) round($htTotal * $escompteRate / 100);

        $adjustment = $this->adjustmentFactory->createWithData(
            self::ESCOMPTE_ADJUSTMENT,
            sprintf('Escompte %.2f%%', $escompteRate),
            $escompteAmount,
        );

        $order->addAdjustment($adjustment);
    }
}
