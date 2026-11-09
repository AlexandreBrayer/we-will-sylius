<?php

declare(strict_types=1);

namespace App\Tests\Yack\Payment\Application\Card;

use App\Entity\Customer\Customer;
use App\Entity\Order\Order;
use App\Entity\Payment\Payment;
use App\Entity\Payment\PaymentMethod;
use App\Entity\Payment\SavedCard;
use App\Yack\Payment\Application\Card\PayableOrder;
use App\Yack\Payment\Application\Card\StartCardPayment;
use App\Yack\Payment\Domain\Card\CardBrand;
use App\Yack\Payment\Domain\Card\CardPaymentAttempt;
use App\Yack\Payment\Domain\Card\CardPaymentGateway;
use App\Yack\Payment\Domain\Card\CardPaymentNotAllowed;
use App\Yack\Payment\Domain\Card\CardPaymentRequest;
use App\Yack\Payment\Domain\Card\CardPaymentSession;
use App\Yack\Payment\Domain\Card\MaskedCard;
use App\Yack\Payment\Domain\Card\SavedCards;
use App\Yack\Payment\Domain\Card\WalletCard;
use App\Yack\Payment\Domain\PaymentTerms;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(StartCardPayment::class)]
#[CoversClass(PayableOrder::class)]
final class StartCardPaymentTest extends TestCase
{
    /** @var list<CardPaymentRequest> */
    private array $requests = [];
    private MockObject&SavedCards $savedCards;
    private StartCardPayment $start;

    #[\Override]
    protected function setUp(): void
    {
        $gateway = $this->createMock(CardPaymentGateway::class);
        $gateway->method('startSession')->willReturnCallback(function (CardPaymentRequest $request): CardPaymentSession {
            $this->requests[] = $request;

            return new CardPaymentSession('s1', '/paiement-securise/s1');
        });
        $this->savedCards = $this->createMock(SavedCards::class);
        $this->start = new StartCardPayment(
            $gateway,
            $this->savedCards,
            self::paymentTerms(),
            $this->createMock(EntityManagerInterface::class),
            new MockClock('2026-11-09 10:00:00'),
            'https://yack.test/',
        );
    }

    public function testItOpensASessionAndRecordsTheAttempt(): void
    {
        $order = self::placedOrder('carte_bancaire');
        $session = $this->start->start($order, self::customer(), null);
        $attempt = CardPaymentAttempt::fromDetails($order->getLastPayment()?->getDetails() ?? []);

        $this->assertSame(
            ['/paiement-securise/s1', '000000042', 37_433, 'ecom@yack.test', 'https://yack.test/commande/retour-paiement/tok42', null, 's1', false],
            [$session->redirectUrl, $this->requests[0]->orderNumber, $this->requests[0]->amount, $this->requests[0]->customerEmail, $this->requests[0]->returnUrl, $this->requests[0]->walletCard, $attempt?->sessionId, $attempt?->oneClick],
        );
    }

    public function testASavedCardIsSentForAOneClickPayment(): void
    {
        $customer = self::customer();
        $this->savedCards->method('getFor')->willReturn(new SavedCard($customer, new WalletCard('simtok_1', new MaskedCard(CardBrand::Visa, '4242', 12, 2030))));
        $order = self::placedOrder('carte_bancaire');
        $this->start->start($order, $customer, 1);

        $this->assertSame(
            ['simtok_1', true, 'Visa •••• 4242'],
            [$this->requests[0]->walletCard?->token, CardPaymentAttempt::fromDetails($order->getLastPayment()?->getDetails() ?? [])?->oneClick, CardPaymentAttempt::fromDetails($order->getLastPayment()?->getDetails() ?? [])?->card?->label()],
        );
    }

    #[DataProvider('notPayable')]
    public function testItRefusesWhatCannotBePaidByCard(string $method, string $paymentState, string $message): void
    {
        $order = self::placedOrder($method);
        $order->setPaymentState($paymentState);

        $this->expectExceptionObject(new CardPaymentNotAllowed($message));

        $this->start->start($order, self::customer(), null);
    }

    /** @return \Iterator<string, array{string, string, string}> */
    public static function notPayable(): \Iterator
    {
        yield 'already paid' => ['carte_bancaire', OrderPaymentStates::STATE_PAID, CardPaymentNotAllowed::orderNotAwaitingPayment()->getMessage()];
        yield 'bank transfer' => ['virement_15j', OrderPaymentStates::STATE_AWAITING_PAYMENT, CardPaymentNotAllowed::notACardPayment()->getMessage()];
    }

    public function testAnExpiredSavedCardIsRefused(): void
    {
        $customer = self::customer();
        $this->savedCards->method('getFor')->willReturn(new SavedCard($customer, new WalletCard('simtok_1', new MaskedCard(CardBrand::Visa, '4242', 10, 2026))));

        $this->expectExceptionObject(CardPaymentNotAllowed::expiredSavedCard());

        $this->start->start(self::placedOrder('carte_bancaire'), $customer, 1);
    }

    public function testAnUnknownSavedCardIsRefused(): void
    {
        $this->expectExceptionObject(CardPaymentNotAllowed::unknownSavedCard());

        $this->start->start(self::placedOrder('carte_bancaire'), self::customer(), 99);
    }

    private static function placedOrder(string $methodCode): Order
    {
        $method = new PaymentMethod();
        $method->setCode($methodCode);
        $payment = new Payment();
        $payment->setMethod($method);
        $payment->setAmount(37_433);
        $payment->setCurrencyCode('EUR');
        $payment->setState(PaymentInterface::STATE_NEW);
        $order = new Order();
        $order->setNumber('000000042');
        $order->setTokenValue('tok42');
        $order->setState(OrderInterface::STATE_NEW);
        $order->setPaymentState(OrderPaymentStates::STATE_AWAITING_PAYMENT);
        $order->addPayment($payment);

        return $order;
    }

    private static function customer(): Customer
    {
        $customer = new Customer();
        $customer->setEmail('ecom@yack.test');

        return $customer;
    }

    private static function paymentTerms(): PaymentTerms
    {
        return new PaymentTerms(
            ['holder' => 'Yack', 'bank' => 'Banque', 'iban' => 'FR7600000000000000000000000', 'bic' => 'BANKFRPP'],
            ['virement_15j' => ['kind' => 'virement', 'due_days' => 15], 'carte_bancaire' => ['kind' => 'carte']],
        );
    }
}
