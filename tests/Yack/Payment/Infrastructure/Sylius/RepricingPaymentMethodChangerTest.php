<?php

declare(strict_types=1);

namespace App\Tests\Yack\Payment\Infrastructure\Sylius;

use App\Entity\Order\Order;
use App\Entity\Payment\Payment;
use App\Entity\Payment\PaymentMethod;
use App\Yack\Payment\Domain\PaymentTerms;
use App\Yack\Payment\Infrastructure\Sylius\PlacedOrderRecalculator;
use App\Yack\Payment\Infrastructure\Sylius\RepricingPaymentMethodChanger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ApiBundle\Changer\PaymentMethodChangerInterface;
use Sylius\Bundle\CoreBundle\Mailer\OrderEmailManagerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

/**
 * @internal
 */
#[CoversClass(RepricingPaymentMethodChanger::class)]
#[CoversClass(PlacedOrderRecalculator::class)]
final class RepricingPaymentMethodChangerTest extends TestCase
{
    /** @var list<string> states of the order seen by the processors */
    private array $processed = [];

    #[DataProvider('changes')]
    public function testANewMethodRepricesTheOrder(string $from, string $to, int $processed, int $emails): void
    {
        $order = self::order($from);
        $mailer = $this->createMock(OrderEmailManagerInterface::class);
        $mailer->expects($this->exactly($emails))->method('sendConfirmationEmail')->with($order);

        $this->changer($mailer)->changePaymentMethod($to, 7, $order);

        $this->assertSame(
            [array_fill(0, $processed, OrderInterface::STATE_CART), OrderInterface::STATE_NEW, $to],
            [$this->processed, $order->getState(), $order->getLastPayment()?->getMethod()?->getCode()],
        );
    }

    /** @return \Iterator<string, array{string, string, int, int}> */
    public static function changes(): \Iterator
    {
        yield 'card refused, bank transfer chosen' => ['carte_bancaire', 'virement_15j', 2, 1];
        yield 'bank transfer, card chosen' => ['virement_15j', 'carte_bancaire', 2, 0];
        yield 'same method' => ['carte_bancaire', 'carte_bancaire', 0, 0];
    }

    public function testTheNewPaymentTakesTheRecomputedTotal(): void
    {
        $order = self::order('carte_bancaire');
        $this->changer($this->createMock(OrderEmailManagerInterface::class))->changePaymentMethod('virement_15j', 7, $order);

        $this->assertSame($order->getTotal(), $order->getLastPayment()?->getAmount());
    }

    private function changer(OrderEmailManagerInterface $mailer): RepricingPaymentMethodChanger
    {
        $inner = $this->createMock(PaymentMethodChangerInterface::class);
        $inner->method('changePaymentMethod')->willReturnCallback(static function (string $code, mixed $paymentId, OrderInterface $order): OrderInterface {
            $method = new PaymentMethod();
            $method->setCode($code);
            $order->getLastPayment()?->setMethod($method);

            return $order;
        });
        $processor = $this->createMock(OrderProcessorInterface::class);
        $processor->method('process')->willReturnCallback(function (OrderInterface $order): void {
            $this->processed[] = $order->getState();
        });

        return new RepricingPaymentMethodChanger($inner, new PlacedOrderRecalculator($processor, $processor), self::paymentTerms(), $mailer);
    }

    private static function order(string $methodCode): Order
    {
        $method = new PaymentMethod();
        $method->setCode($methodCode);
        $payment = new Payment();
        $payment->setMethod($method);
        $payment->setState(PaymentInterface::STATE_NEW);
        $payment->setAmount(1);
        (new \ReflectionProperty($payment, 'id'))->setValue($payment, 7);
        $order = new Order();
        $order->setState(OrderInterface::STATE_NEW);
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
