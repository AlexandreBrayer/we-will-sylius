<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Api;

use App\Yack\Payment\Domain\Card\CardBrand;
use App\Yack\Payment\Domain\Card\MaskedCard;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Card checked by the simulated hosted page, masked: brand, last four digits, expiry.
 */
final class SimulatedCardInput
{
    #[Assert\NotBlank]
    #[Assert\Choice(callback: [self::class, 'brands'])]
    public ?string $brand = null;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{4}$/', message: 'Les 4 derniers chiffres de la carte sont attendus.')]
    public ?string $last4 = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: 12)]
    public ?int $expiryMonth = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 2000, max: 2099)]
    public ?int $expiryYear = null;

    public bool $saveCard = false;

    /** @return list<string> */
    public static function brands(): array
    {
        return array_map(static fn (CardBrand $brand): string => $brand->value, CardBrand::cases());
    }

    public function card(): MaskedCard
    {
        return new MaskedCard(CardBrand::from((string) $this->brand), (string) $this->last4, (int) $this->expiryMonth, (int) $this->expiryYear);
    }
}
