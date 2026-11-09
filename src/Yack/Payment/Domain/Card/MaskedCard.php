<?php

declare(strict_types=1);

namespace App\Yack\Payment\Domain\Card;

/**
 * What Yack may know of a card (PCI DSS: never the number nor the security code): brand, last
 * four digits and expiry, as returned by the payment service provider.
 */
final readonly class MaskedCard
{
    public function __construct(
        public CardBrand $brand,
        public string $last4,
        public int $expiryMonth,
        public int $expiryYear,
    ) {
        if (1 !== preg_match('/^\d{4}$/', $last4)) {
            throw new \InvalidArgumentException('A masked card keeps exactly the last four digits of the card number.');
        }
        if ($expiryMonth < 1 || $expiryMonth > 12 || $expiryYear < 2000 || $expiryYear > 2099) {
            throw new \InvalidArgumentException(sprintf('Invalid card expiry %d/%d.', $expiryMonth, $expiryYear));
        }
    }

    /** "Visa •••• 4242" */
    public function label(): string
    {
        return sprintf('%s •••• %s', $this->brand->label(), $this->last4);
    }

    /** "12/28" */
    public function expiry(): string
    {
        return sprintf('%02d/%02d', $this->expiryMonth, $this->expiryYear % 100);
    }

    /** A card is valid until the last day of its expiry month. */
    public function isExpiredAt(\DateTimeImmutable $date): bool
    {
        return (int) $date->format('Y') * 12 + (int) $date->format('n') > $this->expiryYear * 12 + $this->expiryMonth;
    }

    /** @return array{brand: string, last4: string, expiryMonth: int, expiryYear: int} */
    public function toArray(): array
    {
        return ['brand' => $this->brand->value, 'last4' => $this->last4, 'expiryMonth' => $this->expiryMonth, 'expiryYear' => $this->expiryYear];
    }

    /** @param array<array-key, mixed> $data */
    public static function fromArray(array $data): self
    {
        $brand = $data['brand'] ?? null;
        $last4 = $data['last4'] ?? null;
        $month = $data['expiryMonth'] ?? null;
        $year = $data['expiryYear'] ?? null;
        if (!is_string($brand) || !is_string($last4) || !is_int($month) || !is_int($year)) {
            throw new \InvalidArgumentException('Incomplete masked card.');
        }

        return new self(CardBrand::from($brand), $last4, $month, $year);
    }
}
