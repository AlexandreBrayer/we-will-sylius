<?php

declare(strict_types=1);

namespace App\Yack\Payment\Application\Card;

use App\Entity\Customer\Customer;
use App\Yack\Payment\Domain\Card\CardPaymentAttempt;
use App\Yack\Payment\Domain\Card\CardPaymentGateway;
use App\Yack\Payment\Domain\Card\CardPaymentNotAllowed;
use App\Yack\Payment\Domain\Card\CardPaymentRequest;
use App\Yack\Payment\Domain\Card\CardPaymentSession;
use App\Yack\Payment\Domain\Card\SavedCards;
use App\Yack\Payment\Domain\Card\WalletCard;
use App\Yack\Payment\Domain\PaymentTerms;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Card payment of a placed order: opens a session at the payment service provider (hosted
 * payment page, or a saved card for a one-click payment) and records the attempt in the
 * details of the Sylius payment. The customer is then sent to the session's redirect URL.
 */
final readonly class StartCardPayment
{
    /** Yack page the provider sends the customer back to (front, `{token}` = order token). */
    public const RETURN_PATH = '/commande/retour-paiement/%s';

    public function __construct(
        private CardPaymentGateway $gateway,
        private SavedCards $savedCards,
        private PaymentTerms $paymentTerms,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        #[Autowire(param: 'app.yack.front_url')]
        private string $frontUrl,
    ) {
    }

    /** @throws CardPaymentNotAllowed */
    public function start(OrderInterface $order, Customer $customer, ?int $savedCardId = null): CardPaymentSession
    {
        $payment = PayableOrder::pendingPayment($order) ?? throw CardPaymentNotAllowed::orderNotAwaitingPayment();
        $methodCode = $payment->getMethod()?->getCode();
        if (null === $methodCode || !$this->paymentTerms->isCard($methodCode)) {
            throw CardPaymentNotAllowed::notACardPayment();
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $walletCard = null === $savedCardId ? null : $this->walletCard($customer, $savedCardId, $now);
        $number = (string) $order->getNumber();

        $session = $this->gateway->startSession(new CardPaymentRequest(
            $number,
            sprintf('%s-%s', PaymentTerms::reference($number), (string) $payment->getId()),
            (int) $payment->getAmount(),
            (string) $payment->getCurrencyCode(),
            (string) $customer->getEmail(),
            rtrim($this->frontUrl, '/') . sprintf(self::RETURN_PATH, (string) $order->getTokenValue()),
            $walletCard,
        ));

        $payment->setDetails(CardPaymentAttempt::start($session, $walletCard, $now)->intoDetails($payment->getDetails()));
        $this->entityManager->flush();

        return $session;
    }

    private function walletCard(Customer $customer, int $savedCardId, \DateTimeImmutable $now): WalletCard
    {
        $saved = $this->savedCards->getFor($customer, $savedCardId) ?? throw CardPaymentNotAllowed::unknownSavedCard();
        if ($saved->card()->isExpiredAt($now)) {
            throw CardPaymentNotAllowed::expiredSavedCard();
        }

        return $saved->walletCard();
    }
}
