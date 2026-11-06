<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Card;

use App\Yack\Payment\Domain\PaymentTerms;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * POC stand-in for a card PSP: when the checkout completes with a card payment method, the
 * payment is accepted immediately (Sylius "complete" transition, order payment state "paid").
 * Runs after Sylius has created the order and resolved its payment state (priority 200).
 * A real integration replaces this with the PSP's payment request / notification flow.
 */
#[AsEventListener(event: 'workflow.sylius_order_checkout.completed.complete', priority: 50)]
final readonly class FakeCardPaymentListener
{
    public function __construct(
        private PaymentTerms $paymentTerms,
        private StateMachineInterface $stateMachine,
    ) {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        if (!$order instanceof OrderInterface) {
            return;
        }

        $payment = $order->getLastPayment(PaymentInterface::STATE_NEW);
        $methodCode = $payment?->getMethod()?->getCode();
        if (null === $payment || null === $methodCode || !$this->paymentTerms->isCard($methodCode)) {
            return;
        }

        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE)) {
            $payment->setDetails(['yack_fake_card' => 'Paiement accepté (simulation POC, aucun débit réel)']);
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE);
        }
    }
}
