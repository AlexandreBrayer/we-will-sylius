<?php

declare(strict_types=1);

namespace App\Yack\Payment\Domain;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Escompte (early-payment discount, % of the excl. tax total) granted by payment method.
 *
 * Configured in config/packages/yack.yaml (parameter app.yack.payment_method_escompte_rates):
 * a method listed with 0 explicitly grants no discount; an unlisted method falls back to the
 * customer-level escompte (App\OrderProcessing\EscompteProcessor).
 */
final readonly class PaymentMethodEscompteRates
{
    /** @var array<string, float> */
    private array $rates;

    /** @param array<string, float|int> $ratesByPaymentMethodCode */
    public function __construct(
        #[Autowire(param: 'app.yack.payment_method_escompte_rates')]
        array $ratesByPaymentMethodCode,
    ) {
        $rates = [];
        foreach ($ratesByPaymentMethodCode as $code => $rate) {
            if ($rate < 0 || $rate >= 100) {
                throw new \InvalidArgumentException(sprintf('Invalid escompte rate %s for payment method "%s".', $rate, $code));
            }
            $rates[$code] = (float) $rate;
        }
        $this->rates = $rates;
    }

    public function forPaymentMethod(string $paymentMethodCode): ?float
    {
        return $this->rates[$paymentMethodCode] ?? null;
    }
}
