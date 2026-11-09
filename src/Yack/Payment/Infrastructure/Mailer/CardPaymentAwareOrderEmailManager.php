<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Mailer;

use App\Yack\Payment\Application\Card\PayableOrder;
use App\Yack\Payment\Domain\PaymentTerms;
use Sylius\Bundle\CoreBundle\Mailer\OrderEmailManagerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Order confirmation e-mail of a card order held until the card is paid: Sylius sends it when the
 * order is placed, before the customer reaches the payment page. It leaves once the payment is
 * accepted (ConcludeCardPayment) or when the customer switches to a bank transfer
 * (RepricingPaymentMethodChanger). A resend from the back-office always leaves.
 */
#[AsDecorator('sylius.mailer.order_email_manager')]
final readonly class CardPaymentAwareOrderEmailManager implements OrderEmailManagerInterface
{
    public function __construct(
        #[AutowireDecorated]
        private OrderEmailManagerInterface $inner,
        private PaymentTerms $paymentTerms,
    ) {
    }

    public function sendConfirmationEmail(OrderInterface $order): void
    {
        $methodCode = PayableOrder::pendingPayment($order)?->getMethod()?->getCode();
        if (null !== $methodCode && $this->paymentTerms->isCard($methodCode)) {
            return;
        }

        $this->inner->sendConfirmationEmail($order);
    }

    public function resendConfirmationEmail(OrderInterface $order): void
    {
        $this->inner->resendConfirmationEmail($order);
    }
}
