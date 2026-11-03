<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Twig;

use App\Yack\Payment\Domain\PaymentInstruction;
use App\Yack\Payment\Domain\PaymentTerms;
use Sylius\Component\Core\Model\OrderInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * yack_payment_instruction(order): RIB + reference + due date for a bank transfer, or the
 * card confirmation. Used by the thank-you page, the e-mails and the order details.
 */
final class PaymentInstructionExtension extends AbstractExtension
{
    public function __construct(private readonly PaymentTerms $paymentTerms)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('yack_payment_instruction', $this->instruction(...)),
        ];
    }

    public function instruction(OrderInterface $order): ?PaymentInstruction
    {
        $methodCode = $order->getLastPayment()?->getMethod()?->getCode();
        $number = $order->getNumber();
        if (null === $methodCode || null === $number) {
            return null;
        }

        $orderedAt = $order->getCheckoutCompletedAt() ?? new \DateTimeImmutable();

        return $this->paymentTerms->instructionFor($methodCode, $number, \DateTimeImmutable::createFromInterface($orderedAt));
    }
}
