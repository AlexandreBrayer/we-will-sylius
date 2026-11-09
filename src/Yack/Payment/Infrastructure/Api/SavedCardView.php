<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Payment\SavedCard;

/**
 * Cards saved by the logged-in customer for one-click payments ("Mes cartes" of the account):
 * masked card and expiry only, the provider token stays in Sylius.
 *
 * - GET    /shop/yack/saved-cards       newest first
 * - DELETE /shop/yack/saved-cards/{id}  204; 404 for another customer's card
 */
#[ApiResource(
    shortName: 'YackSavedCard',
    operations: [
        new GetCollection(
            uriTemplate: '/shop/yack/saved-cards',
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            provider: SavedCardProvider::class,
            name: 'app_yack_api_shop_saved_card_index',
        ),
        new Delete(
            uriTemplate: '/shop/yack/saved-cards/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_USER')",
            provider: SavedCardProvider::class,
            processor: SavedCardDeleteProcessor::class,
            name: 'app_yack_api_shop_saved_card_delete',
        ),
    ],
)]
final readonly class SavedCardView
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public int $id,
        public string $brand,
        public string $brandLabel,
        public string $last4,
        /** "Visa •••• 4242" */
        public string $label,
        /** "12/28" */
        public string $expiry,
        public bool $expired,
        public string $createdAt,
    ) {
    }

    public static function of(SavedCard $savedCard, \DateTimeImmutable $now): self
    {
        $card = $savedCard->card();

        return new self(
            (int) $savedCard->getId(),
            $card->brand->value,
            $card->brand->label(),
            $card->last4,
            $card->label(),
            $card->expiry(),
            $card->isExpiredAt($now),
            $savedCard->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
