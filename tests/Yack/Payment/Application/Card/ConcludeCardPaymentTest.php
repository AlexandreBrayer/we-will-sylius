<?php

declare(strict_types=1);

namespace App\Tests\Yack\Payment\Application\Card;

use App\Entity\Customer\Customer;
use App\Entity\Order\Order;
use App\Entity\Payment\Payment;
use App\Entity\Payment\PaymentMethod;
use App\Entity\Payment\SavedCard;
use App\Yack\Payment\Application\Card\ConcludeCardPayment;
use App\Yack\Payment\Domain\Card\CardBrand;
use App\Yack\Payment\Domain\Card\CardPaymentAttempt;
use App\Yack\Payment\Domain\Card\CardPaymentGateway;
use App\Yack\Payment\Domain\Card\CardPaymentNotAllowed;
use App\Yack\Payment\Domain\Card\CardPaymentResult;
use App\Yack\Payment\Domain\Card\CardPaymentSession;
use App\Yack\Payment\Domain\Card\CardPaymentStatus;
use App\Yack\Payment\Domain\Card\MaskedCard;
use App\Yack\Payment\Domain\Card\SavedCards;
use App\Yack\Payment\Domain\Card\WalletCard;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\CoreBundle\Mailer\OrderEmailManagerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(ConcludeCardPayment::class)]
final class ConcludeCardPaymentTest extends TestCase
{
    private CardPaymentResult $result;

    /** @var list<string> */
    private array $transitions = [];
    private int $emails = 0;
    private int $savedCards = 0;
    private ConcludeCardPayment $conclude;

    #[\Override]
    protected function setUp(): void
    {
        $this->result = CardPaymentResult::pending();
        $gateway = $this->createMock(CardPaymentGateway::class);
        $gateway->method('result')->willReturnCallback(fn (): CardPaymentResult => $this->result);
        $stateMachine = $this->createMock(StateMachineInterface::class);
        $stateMachine->method('apply')->willReturnCallback(function (object $subject, string $graph, string $transition): void {
            $this->transitions[] = $graph . '.' . $transition;
            if ($subject instanceof PaymentInterface) {
                $subject->setState(PaymentTransitions::TRANSITION_COMPLETE === $transition ? PaymentInterface::STATE_COMPLETED : PaymentInterface::STATE_FAILED);
                $subject->getOrder()?->setPaymentState(PaymentTransitions::TRANSITION_COMPLETE === $transition ? OrderPaymentStates::STATE_PAID : OrderPaymentStates::STATE_AWAITING_PAYMENT);
            }
        });
        $mailer = $this->createMock(OrderEmailManagerInterface::class);
        $mailer->method('sendConfirmationEmail')->willReturnCallback(function (): void {
            ++$this->emails;
        });
        $savedCards = $this->createMock(SavedCards::class);
        $savedCards->method('save')->willReturnCallback(function (Customer $customer, WalletCard $walletCard): SavedCard {
            ++$this->savedCards;

            return new SavedCard($customer, $walletCard);
        });
        $this->conclude = new ConcludeCardPayment(
            $gateway,
            $savedCards,
            $stateMachine,
            $mailer,
            $this->createMock(EntityManagerInterface::class),
            new MockClock('2026-11-09 10:05:00'),
        );
    }

    /** @param list<string> $transitions */
    #[DataProvider('outcomes')]
    public function testTheOutcomeIsAppliedToTheSyliusPayment(CardPaymentStatus $status, ?string $reason, array $transitions, int $emails): void
    {
        $this->result = new CardPaymentResult($status, 'SIM-1', new MaskedCard(CardBrand::Visa, '4242', 12, 2030), false, $reason);
        $order = self::orderWithAttempt();
        $attempt = $this->conclude->conclude($order);

        $this->assertSame(
            [$status, $transitions, $emails, $status, 'SIM-1'],
            [$attempt->status, $this->transitions, $this->emails, CardPaymentAttempt::fromDetails($order->getPayments()->first()?->getDetails() ?? [])?->status, $attempt->transactionReference],
        );
    }

    /** @return \Iterator<string, array{CardPaymentStatus, ?string, list<string>, int}> */
    public static function outcomes(): \Iterator
    {
        yield 'accepted' => [CardPaymentStatus::Accepted, null, ['sylius_payment.complete'], 1];
        yield 'refused' => [CardPaymentStatus::Refused, 'fonds insuffisants', ['sylius_payment.fail'], 0];
        yield 'cancelled' => [CardPaymentStatus::Cancelled, null, ['sylius_payment.cancel'], 0];
    }

    public function testAPaymentStillOnTheHostedPageChangesNothing(): void
    {
        $attempt = $this->conclude->conclude(self::orderWithAttempt());

        $this->assertSame([CardPaymentStatus::Pending, [], 0], [$attempt->status, $this->transitions, $this->emails]);
    }

    public function testComingBackTwiceGivesTheOutcomeAlreadyApplied(): void
    {
        $this->result = new CardPaymentResult(CardPaymentStatus::Accepted, 'SIM-1', new MaskedCard(CardBrand::Visa, '4242', 12, 2030));
        $order = self::orderWithAttempt();
        $this->conclude->conclude($order);
        $again = $this->conclude->conclude($order);

        $this->assertSame([CardPaymentStatus::Accepted, ['sylius_payment.complete'], 1], [$again->status, $this->transitions, $this->emails]);
    }

    public function testACardAskedToBeSavedIsSavedOnceAccepted(): void
    {
        $this->result = new CardPaymentResult(CardPaymentStatus::Accepted, 'SIM-1', new MaskedCard(CardBrand::Visa, '4242', 12, 2030), false, null, 'simtok_1');

        $attempt = $this->conclude->conclude(self::orderWithAttempt());

        $this->assertSame([1, true], [$this->savedCards, $attempt->cardSaved]);
    }

    public function testAnOrderWithoutCardAttemptCannotBeConcluded(): void
    {
        $order = self::orderWithAttempt();
        $order->getPayments()->first()?->setDetails([]);

        $this->expectExceptionObject(CardPaymentNotAllowed::noSessionInProgress());

        $this->conclude->conclude($order);
    }

    private static function orderWithAttempt(): Order
    {
        $method = new PaymentMethod();
        $method->setCode('carte_bancaire');
        $payment = new Payment();
        $payment->setMethod($method);
        $payment->setAmount(37_433);
        $payment->setState(PaymentInterface::STATE_NEW);
        $payment->setDetails(CardPaymentAttempt::start(new CardPaymentSession('s1', '/paiement-securise/s1'), null, new \DateTimeImmutable('2026-11-09 10:00'))->intoDetails([]));
        $order = new Order();
        $order->setNumber('000000042');
        $order->setState(OrderInterface::STATE_NEW);
        $order->setPaymentState(OrderPaymentStates::STATE_AWAITING_PAYMENT);
        $order->setCustomer(new Customer());
        $order->addPayment($payment);

        return $order;
    }
}
