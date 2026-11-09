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
 * default locale of the Yack channel. Null when the front does not show the product: product disabled, not in
 * the Yack channel or channel disabled, no slug in that locale.
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
        return [new TwigFunction('yack_front_product_url', $this->productUrl(...))];
    }

    public function productUrl(ProductInterface $product): ?string
    {
        $locale = $product->isEnabled() ? $this->shopLocale($product) : null;
        if (null === $locale) {
            return null;
        }

        foreach ($product->getTranslations() as $translation) {
            if (!$translation instanceof ProductTranslationInterface || $translation->getLocale() !== $locale) {
                continue;
            }
            $slug = $translation->getSlug();

            return null === $slug || '' === $slug ? null : rtrim($this->frontUrl, '/') . '/produit/' . rawurlencode($slug);
        }

        return null;
    }

    /** Default locale of the Yack channel, when the product is published in it and the channel is enabled. */
    private function shopLocale(ProductInterface $product): ?string
    {
        foreach ($product->getChannels() as $channel) {
            if ($channel instanceof ChannelInterface && $channel->getCode() === $this->channelCode && $channel->isEnabled()) {
                return $channel->getDefaultLocale()?->getCode();
            }
        }

        return null;
    }
}
