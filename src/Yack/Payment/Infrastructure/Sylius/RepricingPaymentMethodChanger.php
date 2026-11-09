<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Sylius;

use App\Yack\Payment\Domain\PaymentTerms;
use Sylius\Bundle\ApiBundle\Changer\PaymentMethodChangerInterface;
use Sylius\Bundle\CoreBundle\Mailer\OrderEmailManagerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Payment method of a placed order changed (PATCH /shop/account/orders/{token}/payments/{id},
 * e.g. card refused → bank transfer): Sylius only swaps the method; here the escompte, VAT and
 * amount to pay follow the new method. Leaving the card for a bank transfer also sends the
 * order confirmation e-mail, held until then (CardPaymentAwareOrderEmailManager), with Yack's
 * bank details.
 */
#[AsDecorator('sylius_api.changer.payment_method')]
final readonly class RepricingPaymentMethodChanger implements PaymentMethodChangerInterface
{
    public function __construct(
        #[AutowireDecorated]
        private PaymentMethodChangerInterface $inner,
        private PlacedOrderRecalculator $recalculator,
        private PaymentTerms $paymentTerms,
        private OrderEmailManagerInterface $orderEmailManager,
    ) {
    }

    public function changePaymentMethod(string $paymentMethodCode, mixed $paymentId, OrderInterface $order): OrderInterface
    {
        $previous = null;
        foreach ($order->getPayments() as $payment) {
            $id = $payment->getId();
            if (is_scalar($id) && is_scalar($paymentId) && (string) $id === (string) $paymentId) {
                $previous = $payment->getMethod()?->getCode();
            }
        }

        $order = $this->inner->changePaymentMethod($paymentMethodCode, $paymentId, $order);
        if ($previous === $paymentMethodCode) {
            return $order;
        }

        $this->recalculator->recalculate($order);
        if (null !== $previous && $this->paymentTerms->isCard($previous) && !$this->paymentTerms->isCard($paymentMethodCode)) {
            $this->orderEmailManager->sendConfirmationEmail($order);
        }

        return $order;
    }
}
