<?php

declare(strict_types=1);

namespace App\Yack\Payment\Application\Card;

use App\Entity\Customer\Customer;
use App\Yack\Payment\Domain\Card\CardPaymentAttempt;
use App\Yack\Payment\Domain\Card\CardPaymentGateway;
use App\Yack\Payment\Domain\Card\CardPaymentNotAllowed;
use App\Yack\Payment\Domain\Card\CardPaymentStatus;
use App\Yack\Payment\Domain\Card\SavedCards;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\CoreBundle\Mailer\OrderEmailManagerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;

/**
 * Customer back from the payment service provider: the result of the session is read and
 * applied to the Sylius payment.
 *
 * - accepted: payment completed (order paid), card saved when asked, order confirmation e-mail;
 * - refused / cancelled: payment failed / cancelled. Sylius adds a new payment with the same
 *   method: the order stays awaiting payment and can be paid again, by card or bank transfer;
 * - still pending (back without finishing): nothing changes.
 *
 * Coming back twice gives the outcome already applied.
 */
final readonly class ConcludeCardPayment
{
    public function __construct(
        private CardPaymentGateway $gateway,
        private SavedCards $savedCards,
        private StateMachineInterface $stateMachine,
        private OrderEmailManagerInterface $orderEmailManager,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /** @throws CardPaymentNotAllowed when the order never had a card payment attempt */
    public function conclude(OrderInterface $order): CardPaymentAttempt
    {
        $payment = PayableOrder::pendingPayment($order);
        $attempt = null === $payment ? null : CardPaymentAttempt::fromDetails($payment->getDetails());
        if (null === $payment || null === $attempt) {
            return self::latestAttempt($order) ?? throw CardPaymentNotAllowed::noSessionInProgress();
        }

        $result = $this->gateway->result($attempt->sessionId);
        $concluded = $attempt->conclude($result, \DateTimeImmutable::createFromInterface($this->clock->now()));
        if (!$concluded->status->isConcluded()) {
            return $attempt;
        }

        $payment->setDetails($concluded->intoDetails($payment->getDetails()));
        $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, match ($concluded->status) {
            CardPaymentStatus::Accepted => PaymentTransitions::TRANSITION_COMPLETE,
            CardPaymentStatus::Refused => PaymentTransitions::TRANSITION_FAIL,
            default => PaymentTransitions::TRANSITION_CANCEL,
        });

        $walletCard = $result->walletCard();
        $customer = $order->getCustomer();
        if (CardPaymentStatus::Accepted === $concluded->status && null !== $walletCard && $customer instanceof Customer) {
            $this->savedCards->save($customer, $walletCard);
        }
        $this->entityManager->flush();

        if (CardPaymentStatus::Accepted === $concluded->status) {
            $this->orderEmailManager->sendConfirmationEmail($order);
        }

        return $concluded;
    }

    /** Attempt of the most recent payment that has one (outcome already applied). */
    private static function latestAttempt(OrderInterface $order): ?CardPaymentAttempt
    {
        $payments = $order->getPayments()->toArray();
        foreach (array_reverse($payments) as $payment) {
            if ($payment instanceof PaymentInterface && null !== $attempt = CardPaymentAttempt::fromDetails($payment->getDetails())) {
                return $attempt;
            }
        }

        return null;
    }
}
