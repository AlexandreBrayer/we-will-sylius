<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Twig;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTranslationInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Back-office « Voir en boutique » (templates/admin/yack/product/view_in_store.html.twig): product page of the
 * SvelteKit front, {FRONT_URL}/produit/{slug}. The slug is the one the shop API looks up: translation in the
 * default locale of the Yack channel. When the front does not show the product, no URL and the reason, in French,
 * shown next to the disabled button: product disabled, not in the Yack channel, channel disabled, channel without
 * default locale, no slug in that locale.
 */
final class FrontProductUrlExtension extends AbstractExtension
{
    public function __construct(
        #[Autowire('%app.yack.front_url%')]
        private readonly string $frontUrl,
        #[Autowire('%app.yack.channel_code%')]
        private readonly string $channelCode,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('yack_front_product_url', $this->productUrl(...)),
            new TwigFunction('yack_front_product_hidden_reason', $this->hiddenReason(...)),
        ];
    }

    public function productUrl(ProductInterface $product): ?string
    {
        $slug = $this->slugOrReason($product);

        return \is_array($slug) ? rtrim($this->frontUrl, '/') . '/produit/' . rawurlencode($slug[0]) : null;
    }

    /** Why the front does not show the product (« produit désactivé »…), null when it does. */
    public function hiddenReason(ProductInterface $product): ?string
    {
        $slug = $this->slugOrReason($product);

        return \is_string($slug) ? $slug : null;
    }

    /** @return array{string}|string the slug the front looks up, or the reason why there is none */
    private function slugOrReason(ProductInterface $product): array|string
    {
        if (!$product->isEnabled()) {
            return 'produit désactivé';
        }
        $channel = $this->yackChannel($product);
        if (null === $channel) {
            return 'produit hors du canal Yack';
        }
        if (!$channel->isEnabled()) {
            return 'canal Yack désactivé';
        }
        $locale = $channel->getDefaultLocale()?->getCode();
        if (null === $locale) {
            return 'canal Yack sans langue par défaut';
        }

        foreach ($product->getTranslations() as $translation) {
            if ($translation instanceof ProductTranslationInterface && $translation->getLocale() === $locale) {
                $slug = $translation->getSlug();
                if (null !== $slug && '' !== $slug) {
                    return [$slug];
                }
            }
        }

        return 'pas de slug en ' . $locale;
    }

    private function yackChannel(ProductInterface $product): ?ChannelInterface
    {
        foreach ($product->getChannels() as $channel) {
            if ($channel instanceof ChannelInterface && $channel->getCode() === $this->channelCode) {
                return $channel;
            }
        }

        return null;
    }
}
