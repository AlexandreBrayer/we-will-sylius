<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Yack\Payment\Domain\Card\CardPaymentAttempt;

/**
 * Card payment of a placed order of the logged-in customer (ADR 0009). The card itself is typed
 * on the provider's hosted page: these calls never carry a card number.
 *
 * - POST /shop/yack/orders/{tokenValue}/card-payment         {savedCardId?} → 201 {sessionId, redirectUrl}
 * - POST /shop/yack/orders/{tokenValue}/card-payment/result  customer back from the provider: outcome applied to the order
 *
 * 404: not an order of the customer; 409: nothing to pay by card (message to show).
 */
#[ApiResource(
    shortName: 'YackCardPayment',
    operations: [
        new Post(
            uriTemplate: '/shop/yack/orders/{tokenValue}/card-payment',
            status: 201,
            input: CardPaymentStartInput::class,
            output: CardPaymentRedirect::class,
            security: "is_granted('ROLE_USER')",
            processor: StartCardPaymentProcessor::class,
            name: 'app_yack_api_shop_card_payment_start',
        ),
        new Post(
            uriTemplate: '/shop/yack/orders/{tokenValue}/card-payment/result',
            status: 200,
            input: false,
            // read without provider: the data is null, the processor reads the order (see ApiOperationsTest).
            read: true,
            deserialize: false,
            validate: false,
            security: "is_granted('ROLE_USER')",
            processor: CardPaymentResultProcessor::class,
            name: 'app_yack_api_shop_card_payment_result',
        ),
    ],
)]
final readonly class CardPaymentOutcome
{
    /**
     * @param array{brand: string, brandLabel: string, last4: string, label: string, expiry: string}|null $card
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $tokenValue,
        /** pending | accepted | refused | cancelled */
        public string $status,
        /** "Paiement refusé : fonds insuffisants" */
        public string $message,
        public ?string $reason,
        public ?array $card,
        public bool $threeDSecure,
        public bool $oneClick,
        public ?string $transactionReference,
    ) {
    }

    public static function of(string $tokenValue, CardPaymentAttempt $attempt): self
    {
        return new self(
            $tokenValue,
            $attempt->status->value,
            $attempt->message(),
            $attempt->refusalReason,
            CardView::of($attempt->card),
            $attempt->threeDSecure,
            $attempt->oneClick,
            $attempt->transactionReference,
        );
    }
}
