<?php

declare(strict_types=1);

namespace App\Yack\Payment\Application;

use App\Yack\Payment\Domain\PaymentMethodEscompteRates;
use App\Yack\Payment\Domain\PaymentTerms;

final readonly class PaymentMethodTerms implements PaymentMethodTermsInterface
{
    public function __construct(
        private PaymentTerms $paymentTerms,
        private PaymentMethodEscompteRates $escompteRates,
    ) {
    }

    public function bankTransferDueDays(): array
    {
        return $this->paymentTerms->bankTransferDueDays();
    }

    public function escompteRate(string $paymentMethodCode): ?float
    {
        return $this->escompteRates->forPaymentMethod($paymentMethodCode);
    }
}
