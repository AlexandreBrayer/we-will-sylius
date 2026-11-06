<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Api;

use App\Yack\Account\Domain\CommercePermission;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Shop API: the "only ECOM customers see prices" rule applied to every product variant
 * (variant endpoints, product "defaultVariantData", listings). Visitors and PRO customers
 * receive the variant without any price (price fields set to null, catalog promotions removed).
 */
#[AsDecorator('sylius_api.normalizer.product_variant')]
final readonly class PriceHidingProductVariantNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    public const PRICE_FIELDS = ['price', 'originalPrice', 'lowestPriceBeforeDiscount'];

    public function __construct(
        #[AutowireDecorated]
        private NormalizerInterface&NormalizerAwareInterface $inner,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /** @return array<string, mixed>|string|int|float|bool|\ArrayObject<array-key, mixed>|null */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $normalized = $this->inner->normalize($data, $format, $context);
        if (!is_array($normalized) || $this->authorizationChecker->isGranted(CommercePermission::SeePrices->value)) {
            return $normalized;
        }

        foreach (self::PRICE_FIELDS as $field) {
            $normalized[$field] = null;
        }
        unset($normalized['appliedPromotions']);

        return $normalized;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->inner->supportsNormalization($data, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->inner->getSupportedTypes($format);
    }

    public function setNormalizer(NormalizerInterface $normalizer): void
    {
        $this->inner->setNormalizer($normalizer);
    }
}
