<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Card\Simulated;

use App\Yack\Payment\Domain\Card\CardPaymentRequest;
use App\Yack\Payment\Domain\Card\CardPaymentResult;
use App\Yack\Payment\Domain\Card\CardPaymentStatus;
use App\Yack\Payment\Domain\Card\MaskedCard;
use App\Yack\Payment\Domain\Card\WalletCard;

/**
 * Payment session of the simulated provider (what Monext keeps on its side): amount, return
 * URL, step of the customer and, once concluded, the outcome. Holds the masked card only.
 */
final class SimulatedSession
{
    /** Code "sent by SMS" on the simulated 3-D Secure page, shown on that page. */
    public const CHALLENGE_CODE = '123456';

    private SimulatedStep $step = SimulatedStep::Card;
    private ?MaskedCard $card = null;
    private bool $oneClick = false;
    private bool $saveCard = false;
    private bool $threeDSecure = false;
    private ?string $refusalReason = null;
    private ?string $transactionReference = null;
    private ?string $walletToken = null;

    public function __construct(
        public readonly string $id,
        public readonly CardPaymentRequest $request,
    ) {
    }

    public function step(): SimulatedStep
    {
        return $this->step;
    }

    public function card(): ?MaskedCard
    {
        return $this->card;
    }

    public function isOneClick(): bool
    {
        return $this->oneClick;
    }

    public function refusalReason(): ?string
    {
        return $this->refusalReason;
    }

    /** Card typed on the hosted page (number checked by the page, only its masked form comes here). */
    public function submitCard(MaskedCard $card, bool $saveCard, SimulatedIds $ids): void
    {
        $this->assertStep(SimulatedStep::Card);
        $this->card = $card;
        $this->saveCard = $saveCard;
        $this->play(SimulatedScenario::forCard($card), $ids);
    }

    /** One-click payment with a card saved at the provider. */
    public function payWithWalletCard(WalletCard $walletCard, SimulatedIds $ids): void
    {
        $this->assertStep(SimulatedStep::Card);
        $this->card = $walletCard->card;
        $this->oneClick = true;
        $this->play(SimulatedScenario::forCard($walletCard->card), $ids);
    }

    /** Code typed on the 3-D Secure page: a wrong code refuses the payment. */
    public function answerChallenge(string $code, SimulatedIds $ids): void
    {
        $this->assertStep(SimulatedStep::Challenge);
        $this->threeDSecure = true;
        if (self::CHALLENGE_CODE === trim($code)) {
            $this->accept($ids);

            return;
        }

        $this->refuse('authentification 3-D Secure échouée (code incorrect)', $ids);
    }

    public function cancel(): void
    {
        if (!$this->step->isOpen()) {
            throw new \DomainException('Ce paiement est déjà terminé.');
        }
        $this->step = SimulatedStep::Cancelled;
    }

    public function result(): CardPaymentResult
    {
        return match ($this->step) {
            SimulatedStep::Card, SimulatedStep::Challenge => CardPaymentResult::pending(),
            SimulatedStep::Accepted => new CardPaymentResult(CardPaymentStatus::Accepted, $this->transactionReference, $this->card, $this->threeDSecure, null, $this->walletToken),
            SimulatedStep::Refused => new CardPaymentResult(CardPaymentStatus::Refused, $this->transactionReference, $this->card, $this->threeDSecure, $this->refusalReason),
            SimulatedStep::Cancelled => new CardPaymentResult(CardPaymentStatus::Cancelled, null, $this->card, $this->threeDSecure),
        };
    }

    private function play(SimulatedScenario $scenario, SimulatedIds $ids): void
    {
        match ($scenario) {
            SimulatedScenario::Accepted => $this->accept($ids),
            SimulatedScenario::Challenge => $this->step = SimulatedStep::Challenge,
            default => $this->refuse((string) $scenario->refusalReason(), $ids),
        };
    }

    private function accept(SimulatedIds $ids): void
    {
        $this->step = SimulatedStep::Accepted;
        $this->transactionReference = $ids->transactionReference();
        $this->walletToken = $this->saveCard ? $ids->walletToken() : null;
    }

    private function refuse(string $reason, SimulatedIds $ids): void
    {
        $this->step = SimulatedStep::Refused;
        $this->transactionReference = $ids->transactionReference();
        $this->refusalReason = $reason;
    }

    private function assertStep(SimulatedStep $expected): void
    {
        if ($expected !== $this->step) {
            throw new \DomainException($this->step->isOpen() ? 'Étape de paiement inattendue.' : 'Ce paiement est déjà terminé.');
        }
    }
}
