<?php

declare(strict_types=1);

namespace App\Tests\Yack\Payment\Infrastructure\Mailer;

use App\Entity\Order\Order;
use App\Entity\Payment\Payment;
use App\Entity\Payment\PaymentMethod;
use App\Yack\Payment\Domain\PaymentTerms;
use App\Yack\Payment\Infrastructure\Mailer\CardPaymentAwareOrderEmailManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\CoreBundle\Mailer\OrderEmailManagerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;

/**
 * @internal
 */
#[CoversClass(CardPaymentAwareOrderEmailManager::class)]
final class CardPaymentAwareOrderEmailManagerTest extends TestCase
{
    #[DataProvider('orders')]
    public function testTheConfirmationWaitsForTheCardPayment(string $methodCode, string $paymentState, string $orderPaymentState, int $sent): void
    {
        $inner = $this->createMock(OrderEmailManagerInterface::class);
        $inner->expects($this->exactly($sent))->method('sendConfirmationEmail');

        (new CardPaymentAwareOrderEmailManager($inner, self::paymentTerms()))->sendConfirmationEmail(self::order($methodCode, $paymentState, $orderPaymentState));
    }

    /** @return \Iterator<string, array{string, string, string, int}> */
    public static function orders(): \Iterator
    {
        yield 'card awaiting payment' => ['carte_bancaire', PaymentInterface::STATE_NEW, OrderPaymentStates::STATE_AWAITING_PAYMENT, 0];
        yield 'card paid' => ['carte_bancaire', PaymentInterface::STATE_COMPLETED, OrderPaymentStates::STATE_PAID, 1];
        yield 'bank transfer' => ['virement_15j', PaymentInterface::STATE_NEW, OrderPaymentStates::STATE_AWAITING_PAYMENT, 1];
    }

    public function testAResendIsAlwaysSent(): void
    {
        $inner = $this->createMock(OrderEmailManagerInterface::class);
        $inner->expects($this->once())->method('resendConfirmationEmail');

        (new CardPaymentAwareOrderEmailManager($inner, self::paymentTerms()))->resendConfirmationEmail(self::order('carte_bancaire', PaymentInterface::STATE_NEW, OrderPaymentStates::STATE_AWAITING_PAYMENT));
    }

    private static function order(string $methodCode, string $paymentState, string $orderPaymentState): Order
    {
        $method = new PaymentMethod();
        $method->setCode($methodCode);
        $payment = new Payment();
        $payment->setMethod($method);
        $payment->setState($paymentState);
        $order = new Order();
        $order->setState(OrderInterface::STATE_NEW);
        $order->setPaymentState($orderPaymentState);
        $order->addPayment($payment);

        return $order;
    }

    private static function paymentTerms(): PaymentTerms
    {
        return new PaymentTerms(
            ['holder' => 'Yack', 'bank' => 'Banque', 'iban' => 'FR7600000000000000000000000', 'bic' => 'BANKFRPP'],
            ['virement_15j' => ['kind' => 'virement', 'due_days' => 15], 'carte_bancaire' => ['kind' => 'carte']],
        );
    }
}
