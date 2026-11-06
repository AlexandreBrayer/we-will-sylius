<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Twig;

use App\Yack\Payment\Domain\PaymentMethodEscompteRates;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PaymentEscompteExtension extends AbstractExtension
{
    public function __construct(private readonly PaymentMethodEscompteRates $rates)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('yack_payment_escompte_label', $this->label(...)),
        ];
    }

    /** "Escompte 2 %" for a discounted payment method, null otherwise. */
    public function label(?string $paymentMethodCode): ?string
    {
        return null === $paymentMethodCode ? null : $this->rates->labelFor($paymentMethodCode);
    }
}
