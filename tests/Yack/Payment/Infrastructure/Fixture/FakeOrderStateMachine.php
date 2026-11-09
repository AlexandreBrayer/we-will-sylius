<?php

declare(strict_types=1);

namespace App\Tests\Yack\Payment\Infrastructure\Fixture;

use App\Entity\Payment\Payment;
use App\Entity\Shipping\Shipment;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\ShipmentInterface;

/**
 * The few Sylius transitions the demo order fixture uses, with their main effects (shipment and payment
 * created by the order processors at the address step, order number at completion, a card paid at once
 * like the simulated PSP, cancellation of what is still open). Records every transition applied.
 */
final class FakeOrderStateMachine implements StateMachineInterface
{
    /** @var list<string> */
    public array $applied = [];

    private int $numbers = 0;

    public function can(object $subject, string $graphName, string $transition): bool
    {
        return match ($graphName . '.' . $transition) {
            'sylius_payment.complete' => $subject instanceof PaymentInterface && PaymentInterface::STATE_NEW === $subject->getState(),
            'sylius_payment.refund' => $subject instanceof PaymentInterface && PaymentInterface::STATE_COMPLETED === $subject->getState(),
            'sylius_shipment.ship' => $subject instanceof ShipmentInterface && ShipmentInterface::STATE_READY === $subject->getState(),
            'sylius_order.cancel' => $subject instanceof OrderInterface && OrderInterface::STATE_NEW === $subject->getState(),
            default => true,
        };
    }

    public function apply(object $subject, string $graphName, string $transition, array $context = []): void
    {
        $this->applied[] = $graphName . '.' . $transition;
        if ($subject instanceof OrderInterface && 'sylius_order_checkout.address' === $graphName . '.' . $transition) {
            $subject->addShipment(new Shipment());
            $subject->addPayment(new Payment());
        }
        if ($subject instanceof OrderInterface && 'sylius_order_checkout.complete' === $graphName . '.' . $transition) {
            $subject->setState(OrderInterface::STATE_NEW);
            $subject->setNumber(sprintf('%09d', ++$this->numbers));
            $subject->setCheckoutCompletedAt(new \DateTime());
            foreach ($subject->getShipments() as $shipment) {
                $shipment->setState(ShipmentInterface::STATE_READY);
            }
            foreach ($subject->getPayments() as $payment) {
                $payment->setState('carte' === $payment->getMethod()?->getCode() ? PaymentInterface::STATE_COMPLETED : PaymentInterface::STATE_NEW);
            }
        }
        if ($subject instanceof PaymentInterface) {
            $subject->setState('complete' === $transition ? PaymentInterface::STATE_COMPLETED : PaymentInterface::STATE_REFUNDED);
        }
        if ($subject instanceof ShipmentInterface) {
            $subject->setState(ShipmentInterface::STATE_SHIPPED);
            $subject->setShippedAt(new \DateTime());
        }
        if ($subject instanceof OrderInterface && 'sylius_order.cancel' === $graphName . '.' . $transition) {
            $subject->setState(OrderInterface::STATE_CANCELLED);
            foreach ($subject->getShipments() as $shipment) {
                $shipment->setState(ShipmentInterface::STATE_CANCELLED);
            }
        }
    }

    public function getEnabledTransitions(object $subject, string $graphName): array
    {
        return [];
    }

    public function getTransitionFromState(object $subject, string $graphName, string $fromState): ?string
    {
        return null;
    }

    public function getTransitionToState(object $subject, string $graphName, string $toState): ?string
    {
        return null;
    }
}
