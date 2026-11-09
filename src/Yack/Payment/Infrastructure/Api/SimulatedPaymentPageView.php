<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\Yack\Payment\Infrastructure\Card\Simulated\SimulatedSession;
use App\Yack\Payment\Infrastructure\Card\Simulated\SimulatedStep;

/**
 * Simulated provider (ADR 0009): what the hosted payment page of the front (/paiement-securise/{id})
 * reads and sends. Public like a provider's page: the session id is the secret. The page checks
 * the card number itself and sends only the masked card.
 *
 * - GET  /shop/yack/simulated-monext/sessions/{id}            merchant, order, amount, step (card | challenge | accepted | refused | cancelled)
 * - POST /shop/yack/simulated-monext/sessions/{id}/card       {brand, last4, expiryMonth, expiryYear, saveCard}
 * - POST /shop/yack/simulated-monext/sessions/{id}/challenge  {code} (3-D Secure, code shown on the page)
 * - POST /shop/yack/simulated-monext/sessions/{id}/cancel
 *
 * 404: unknown or expired session; 409: the session is not at that step.
 */
#[ApiResource(
    shortName: 'YackSimulatedPaymentPage',
    operations: [
        new Get(
            uriTemplate: '/shop/yack/simulated-monext/sessions/{id}',
            requirements: ['id' => '[a-f0-9]{32}'],
            provider: SimulatedPaymentPageProvider::class,
            name: 'app_yack_api_shop_simulated_payment_page_get',
        ),
        new Post(
            uriTemplate: '/shop/yack/simulated-monext/sessions/{id}/card',
            requirements: ['id' => '[a-f0-9]{32}'],
            status: 200,
            input: SimulatedCardInput::class,
            processor: SimulatedPaymentPageProcessor::class,
            name: SimulatedPaymentPageProcessor::CARD,
        ),
        new Post(
            uriTemplate: '/shop/yack/simulated-monext/sessions/{id}/challenge',
            requirements: ['id' => '[a-f0-9]{32}'],
            status: 200,
            input: SimulatedChallengeInput::class,
            processor: SimulatedPaymentPageProcessor::class,
            name: SimulatedPaymentPageProcessor::CHALLENGE,
        ),
        new Post(
            uriTemplate: '/shop/yack/simulated-monext/sessions/{id}/cancel',
            requirements: ['id' => '[a-f0-9]{32}'],
            status: 200,
            input: false,
            // read without provider: the data is null, the processor reads the session (see ApiOperationsTest).
            read: true,
            deserialize: false,
            validate: false,
            processor: SimulatedPaymentPageProcessor::class,
            name: SimulatedPaymentPageProcessor::CANCEL,
        ),
    ],
)]
final readonly class SimulatedPaymentPageView
{
    /**
     * @param array{brand: string, brandLabel: string, last4: string, label: string, expiry: string}|null $card
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $merchant,
        public string $orderNumber,
        public string $reference,
        /** In cents, taxes included. */
        public int $amount,
        public string $currencyCode,
        public string $customerEmail,
        /** card | challenge | accepted | refused | cancelled */
        public string $step,
        public ?array $card,
        public bool $oneClick,
        public ?string $refusalReason,
        /** Code of the simulated 3-D Secure page (shown on it), null outside that step. */
        public ?string $challengeCode,
        /** Yack page to go back to once the session is over. */
        public string $returnUrl,
    ) {
    }

    public static function of(SimulatedSession $session): self
    {
        $request = $session->request;

        return new self(
            $session->id,
            'Yack',
            $request->orderNumber,
            $request->reference,
            $request->amount,
            $request->currencyCode,
            $request->customerEmail,
            $session->step()->value,
            CardView::of($session->card()),
            $session->isOneClick(),
            $session->refusalReason(),
            SimulatedStep::Challenge === $session->step() ? SimulatedSession::CHALLENGE_CODE : null,
            $request->returnUrl,
        );
    }
}
