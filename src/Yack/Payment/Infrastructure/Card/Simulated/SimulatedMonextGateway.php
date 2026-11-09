<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Card\Simulated;

use App\Yack\Payment\Domain\Card\CardPaymentGateway;
use App\Yack\Payment\Domain\Card\CardPaymentRequest;
use App\Yack\Payment\Domain\Card\CardPaymentResult;
use App\Yack\Payment\Domain\Card\CardPaymentSession;
use App\Yack\Payment\Domain\Card\CardPaymentStatus;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Card payment provider simulated after Monext / Payline's hosted payment page (ADR 0009): no
 * key, no external call, outcome chosen by the test card (SimulatedScenario).
 *
 * - new card: the customer goes to the simulated hosted page of the front
 *   (/paiement-securise/{session}, operations of SimulatedPaymentPage);
 * - saved card (one-click): paid at once and back to Yack, or sent to the 3-D Secure step of
 *   the hosted page when the test card asks for it.
 */
#[AsAlias(CardPaymentGateway::class)]
final readonly class SimulatedMonextGateway implements CardPaymentGateway
{
    /** Hosted payment page of the simulator, served by the front. */
    public const HOSTED_PAGE_PATH = '/paiement-securise/%s';

    public function __construct(
        private SimulatedSessions $sessions,
        private SimulatedIds $ids,
    ) {
    }

    public function startSession(CardPaymentRequest $request): CardPaymentSession
    {
        $session = new SimulatedSession($this->ids->session(), $request);
        if (null !== $request->walletCard) {
            $session->payWithWalletCard($request->walletCard, $this->ids);
        }
        $this->sessions->save($session);

        $redirectUrl = $session->step()->isOpen() ? sprintf(self::HOSTED_PAGE_PATH, $session->id) : $request->returnUrl;

        return new CardPaymentSession($session->id, $redirectUrl);
    }

    /** An unknown (expired) session counts as cancelled: the order can be paid again. */
    public function result(string $sessionId): CardPaymentResult
    {
        return $this->sessions->get($sessionId)?->result()
            ?? new CardPaymentResult(CardPaymentStatus::Cancelled, refusalReason: 'session de paiement expirée');
    }
}
