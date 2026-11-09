<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Card\Simulated;

use App\Yack\Payment\Domain\Card\MaskedCard;

/**
 * Server side of the simulated hosted payment page (front route /paiement-securise/{session}):
 * the page checks the card (Luhn, expiry, security code) and sends only its masked form here.
 *
 * @throws \DomainException when the session is not at the expected step
 */
final readonly class SimulatedPaymentPage
{
    public function __construct(
        private SimulatedSessions $sessions,
        private SimulatedIds $ids,
    ) {
    }

    public function find(string $sessionId): ?SimulatedSession
    {
        return $this->sessions->get($sessionId);
    }

    public function submitCard(SimulatedSession $session, MaskedCard $card, bool $saveCard): void
    {
        $session->submitCard($card, $saveCard, $this->ids);
        $this->sessions->save($session);
    }

    public function answerChallenge(SimulatedSession $session, string $code): void
    {
        $session->answerChallenge($code, $this->ids);
        $this->sessions->save($session);
    }

    public function cancel(SimulatedSession $session): void
    {
        $session->cancel();
        $this->sessions->save($session);
    }
}
