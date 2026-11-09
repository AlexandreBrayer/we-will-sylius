<?php

declare(strict_types=1);

namespace App\Yack\Payment\Domain\Card;

/**
 * Port: card payment service provider with a hosted payment page (Monext / Payline in the
 * target). The card is typed on the provider's page: Yack only ever receives the outcome and
 * the masked card (ADR 0009).
 *
 * Adapter by default: Infrastructure/Card/Simulated/SimulatedMonextGateway (no key, no call).
 */
interface CardPaymentGateway
{
    /** Opens a payment session; the customer is redirected to the returned URL. */
    public function startSession(CardPaymentRequest $request): CardPaymentSession;

    /** Result of a session, read when the customer comes back to Yack. */
    public function result(string $sessionId): CardPaymentResult;
}
