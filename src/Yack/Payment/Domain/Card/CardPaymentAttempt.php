<?php

declare(strict_types=1);

namespace App\Yack\Payment\Domain\Card;

/**
 * One card payment attempt of a Sylius payment, kept in the payment details (key `yack_card`):
 * provider session, outcome and masked card, shown in the back-office and the customer's
 * order. Never holds a card number.
 */
final readonly class CardPaymentAttempt
{
    public const DETAILS_KEY = 'yack_card';

    public function __construct(
        public string $sessionId,
        public CardPaymentStatus $status,
        public \DateTimeImmutable $startedAt,
        /** Paid with a card saved at the provider. */
        public bool $oneClick = false,
        public ?\DateTimeImmutable $concludedAt = null,
        public ?string $transactionReference = null,
        public ?MaskedCard $card = null,
        public bool $threeDSecure = false,
        public ?string $refusalReason = null,
        /** The customer saved the card at the provider during this attempt. */
        public bool $cardSaved = false,
    ) {
    }

    public static function start(CardPaymentSession $session, ?WalletCard $walletCard, \DateTimeImmutable $at): self
    {
        return new self($session->id, CardPaymentStatus::Pending, $at, null !== $walletCard, card: $walletCard?->card);
    }

    /** Outcome read from the provider; a pending result leaves the attempt as it is. */
    public function conclude(CardPaymentResult $result, \DateTimeImmutable $at): self
    {
        if (!$result->status->isConcluded()) {
            return $this;
        }

        return new self(
            $this->sessionId,
            $result->status,
            $this->startedAt,
            $this->oneClick,
            $at,
            $result->transactionReference,
            $result->card ?? $this->card,
            $result->threeDSecure,
            $result->refusalReason,
            null !== $result->walletCard(),
        );
    }

    /** "Paiement refusé : fonds insuffisants" */
    public function message(): string
    {
        return null === $this->refusalReason ? $this->status->label() : sprintf('%s : %s', $this->status->label(), $this->refusalReason);
    }

    /** @param array<array-key, mixed> $details Details of a Sylius payment */
    public static function fromDetails(array $details): ?self
    {
        $data = $details[self::DETAILS_KEY] ?? null;
        if (!is_array($data) || !is_string($data['session'] ?? null)) {
            return null;
        }

        $status = $data['status'] ?? null;
        $startedAt = $data['startedAt'] ?? null;
        $card = $data['card'] ?? null;
        $concludedAt = $data['concludedAt'] ?? null;

        return new self(
            $data['session'],
            CardPaymentStatus::tryFrom(is_string($status) ? $status : '') ?? CardPaymentStatus::Pending,
            new \DateTimeImmutable(is_string($startedAt) ? $startedAt : 'now'),
            true === ($data['oneClick'] ?? false),
            is_string($concludedAt) ? new \DateTimeImmutable($concludedAt) : null,
            is_string($data['reference'] ?? null) ? $data['reference'] : null,
            is_array($card) ? MaskedCard::fromArray($card) : null,
            true === ($data['threeDSecure'] ?? false),
            is_string($data['reason'] ?? null) ? $data['reason'] : null,
            true === ($data['cardSaved'] ?? false),
        );
    }

    /**
     * @param array<array-key, mixed> $details Details of the Sylius payment, other keys kept
     *
     * @return array<array-key, mixed>
     */
    public function intoDetails(array $details): array
    {
        $details[self::DETAILS_KEY] = [
            'session' => $this->sessionId,
            'status' => $this->status->value,
            'startedAt' => $this->startedAt->format(\DateTimeInterface::ATOM),
            'oneClick' => $this->oneClick,
            'concludedAt' => $this->concludedAt?->format(\DateTimeInterface::ATOM),
            'reference' => $this->transactionReference,
            'card' => $this->card?->toArray(),
            'threeDSecure' => $this->threeDSecure,
            'reason' => $this->refusalReason,
            'cardSaved' => $this->cardSaved,
        ];

        return $details;
    }
}
