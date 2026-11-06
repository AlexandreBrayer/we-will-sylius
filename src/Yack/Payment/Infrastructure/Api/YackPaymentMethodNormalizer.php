<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Api;

use App\Yack\Payment\Domain\PaymentMethodEscompteRates;
use Sylius\Bundle\ApiBundle\SectionResolver\ShopApiSection;
use Sylius\Bundle\CoreBundle\SectionResolver\SectionProviderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Shop API payment methods (list and checkout choices): escompte granted by the method,
 * e.g. "virement_15j" → escompteRate 2.0, escompteLabel "Escompte 2 %".
 */
final class YackPaymentMethodNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'yack_payment_method_normalizer_already_called';

    public function __construct(
        #[Autowire(service: 'sylius.section_resolver.uri_based')]
        private readonly SectionProviderInterface $sectionProvider,
        private readonly PaymentMethodEscompteRates $escompteRates,
    ) {
    }

    /** @return array<string, mixed>|string|int|float|bool|\ArrayObject<array-key, mixed>|null */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        \assert($data instanceof PaymentMethodInterface);
        $context[self::ALREADY_CALLED] = true;

        $normalized = $this->normalizer->normalize($data, $format, $context);
        if (!is_array($normalized)) {
            return $normalized;
        }

        $code = (string) $data->getCode();
        $normalized['escompteRate'] = $this->escompteRates->forPaymentMethod($code);
        $normalized['escompteLabel'] = $this->escompteRates->labelFor($code);

        return $normalized;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) &&
            $data instanceof PaymentMethodInterface &&
            $this->sectionProvider->getSection() instanceof ShopApiSection;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [PaymentMethodInterface::class => false];
    }
}
