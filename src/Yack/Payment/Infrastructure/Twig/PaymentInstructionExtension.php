<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Twig;

use App\Yack\Payment\Application\OrderPaymentInstruction;
use App\Yack\Payment\Domain\Card\CardPaymentAttempt;
use App\Yack\Payment\Domain\PaymentInstruction;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * yack_payment_instruction(order): RIB + reference + due date for a bank transfer, or the
 * card confirmation. yack_card_payment(payment): card attempt of a payment (outcome, masked
 * card, 3-D Secure, transaction reference). Used by the e-mails and the back-office.
 */
final class PaymentInstructionExtension extends AbstractExtension
{
    public function __construct(private readonly OrderPaymentInstruction $orderPaymentInstruction)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('yack_payment_instruction', $this->instruction(...)),
            new TwigFunction('yack_card_payment', $this->cardPayment(...)),
        ];
    }

    public function instruction(OrderInterface $order): ?PaymentInstruction
    {
        return $this->orderPaymentInstruction->forOrder($order);
    }

    public function cardPayment(?PaymentInterface $payment): ?CardPaymentAttempt
    {
        return null === $payment ? null : CardPaymentAttempt::fromDetails($payment->getDetails());
    }
}
