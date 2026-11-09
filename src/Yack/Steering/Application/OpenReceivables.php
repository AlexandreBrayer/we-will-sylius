<?php

declare(strict_types=1);

namespace App\Yack\Steering\Application;

use App\Yack\Payment\Application\PaymentMethodTermsInterface;
use App\Yack\Steering\Domain\Receivable;
use App\Yack\Steering\Domain\SalesFacts;

/**
 * Échéances non réglées : unpaid orders of the bank transfer methods of app.yack.payment_terms.methods, each due the
 * days of its method after the order. A card is paid at once: it never makes an échéance.
 */
final readonly class OpenReceivables
{
    public function __construct(
        private SalesFacts $facts,
        private PaymentMethodTermsInterface $paymentTerms,
    ) {
    }

    /** @return list<Receivable> */
    public function all(): array
    {
        $dueDays = $this->paymentTerms->bankTransferDueDays();
        if ([] === $dueDays) {
            return [];
        }

        $receivables = [];
        foreach ($this->facts->unpaidOrders(array_keys($dueDays)) as $order) {
            $days = $dueDays[$order->paymentMethodCode] ?? null;
            if (null !== $days) {
                $receivables[] = Receivable::fromOrder($order, $days);
            }
        }

        return $receivables;
    }
}
