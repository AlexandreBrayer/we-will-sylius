<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Twig;

use App\Entity\Content\CmsPage;
use App\Yack\Content\Domain\CmsPages;
use App\Yack\Content\Domain\PageBlocks;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * CMS pages inside Sylius: shop homepage ("accueil" rendered from its blocks), shop menu /
 * footer links, back-office page list. Block templates: templates/shop/yack/cms/.
 */
final class CmsExtension extends AbstractExtension
{
    /**
     * @param ProductRepositoryInterface<ProductInterface> $productRepository
     * @param TaxonRepositoryInterface<TaxonInterface> $taxonRepository
     */
    public function __construct(
        private readonly CmsPages $pages,
        private readonly ChannelContextInterface $channelContext,
        #[Autowire(service: 'sylius.repository.product')]
        private readonly ProductRepositoryInterface $productRepository,
        #[Autowire(service: 'sylius.repository.taxon')]
        private readonly TaxonRepositoryInterface $taxonRepository,
        #[Autowire(param: 'app.yack.front_url')]
        private readonly string $frontUrl,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('yack_cms_page', $this->pages->findPublished(...)),
            new TwigFunction('yack_cms_pages', $this->pages->published(...)),
            new TwigFunction('yack_cms_all_pages', $this->pages->all(...)),
            new TwigFunction('yack_cms_url', $this->url(...)),
            new TwigFunction('yack_cms_builder_url', fn (?string $slug = null): string => $this->frontUrl . '/cms-admin/builder' . (null === $slug ? '' : '/' . rawurlencode($slug))),
            new TwigFunction('yack_cms_link', $this->link(...)),
            new TwigFunction('yack_cms_products', $this->products(...)),
            new TwigFunction('yack_cms_taxons', $this->taxons(...)),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('yack_cms_faq', PageBlocks::faq(...)),
        ];
    }

    /** Public URL of a page on the SvelteKit front ("accueil" is the front homepage). */
    public function url(CmsPage|string $page, bool $preview = false): string
    {
        $slug = $page instanceof CmsPage ? $page->getSlug() : $page;

        return $this->frontUrl . '/' . rawurlencode($slug) . ($preview ? '?apercu=1' : '');
    }

    /**
     * Block link convention (same as front/src/lib/blocks/parse.ts): "page:<slug>" = CMS page,
     * anything else (front path "/sav", "https://…") is used as is.
     */
    public function link(string $value): string
    {
        $value = trim($value);

        return str_starts_with($value, 'page:') ? $this->url(substr($value, 5)) : $value;
    }

    /**
     * @return list<ProductInterface> enabled products of the current channel, in the given order
     */
    public function products(string $codes): array
    {
        $channel = $this->channelContext->getChannel();
        $products = [];
        foreach (PageBlocks::codes($codes) as $code) {
            $product = $this->productRepository->findOneByCode($code);
            if ($product instanceof ProductInterface && $product->isEnabled() && $product->hasChannel($channel)) {
                $products[] = $product;
            }
        }

        return $products;
    }

    /**
     * @return list<TaxonInterface>
     */
    public function taxons(string $codes): array
    {
        $taxons = [];
        foreach (PageBlocks::codes($codes) as $code) {
            $taxon = $this->taxonRepository->findOneBy(['code' => $code]);
            if ($taxon instanceof TaxonInterface && $taxon->isEnabled()) {
                $taxons[] = $taxon;
            }
        }

        return $taxons;
    }
}
