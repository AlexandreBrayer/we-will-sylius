<?php

declare(strict_types=1);

namespace App\Yack\Payment\Application;

/**
 * Public entry point of Payment for the terms of the payment methods (ADR 008): days to pay a bank transfer
 * (app.yack.payment_terms.methods) and escompte rate (app.yack.payment_method_escompte_rates). Read by Steering.
 */
interface PaymentMethodTermsInterface
{
    /**
     * Bank transfer methods (code => days between the order and its échéance).
     *
     * @return array<string, int>
     */
    public function bankTransferDueDays(): array;

    /** Escompte rate (% of the excl. tax amount) of a method, null when the method falls back to the customer's own. */
    public function escompteRate(string $paymentMethodCode): ?float;
}
