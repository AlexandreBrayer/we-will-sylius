<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Twig;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsTemplate;
use App\Entity\Product\Product;
use App\Entity\Taxonomy\Taxon;
use App\Yack\Content\Application\CmsTemplateResolver;
use App\Yack\Content\Domain\ResolvedCmsTemplate;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Back-office (templates/admin/yack/cms_page/, cms_template/, product and taxon form sections):
 * links to the SvelteKit front, which renders the pages and hosts the builder, and effective
 * CMS templates of the edited product / taxon.
 */
final class CmsExtension extends AbstractExtension
{
    public function __construct(
        #[Autowire(param: 'app.yack.front_url')]
        private readonly string $frontUrl,
        private readonly CmsTemplateResolver $templateResolver,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('yack_cms_url', $this->url(...)),
            new TwigFunction('yack_cms_builder_url', $this->builderUrl(...)),
            new TwigFunction('yack_cms_template_builder_url', $this->templateBuilderUrl(...)),
            new TwigFunction('yack_cms_template_inherited', $this->inheritedTemplate(...)),
        ];
    }

    /** Public URL of a page on the SvelteKit front (`?apercu=1`: draft preview for the builder session). */
    public function url(CmsPage|string $page, bool $preview = false): string
    {
        $slug = $page instanceof CmsPage ? $page->getSlug() : $page;

        return $this->frontUrl . '/' . rawurlencode($slug) . ($preview ? '?apercu=1' : '');
    }

    public function builderUrl(?string $slug = null): string
    {
        return $this->frontUrl . '/cms-admin/builder' . (null === $slug ? '' : '/' . rawurlencode($slug));
    }

    /** Builder: template list (null) or template editor. */
    public function templateBuilderUrl(CmsTemplate|int|null $template = null): string
    {
        $id = $template instanceof CmsTemplate ? $template->getId() : $template;

        return $this->frontUrl . '/cms-admin/builder/templates' . (null === $id ? '' : '/' . $id);
    }

    /**
     * Template applied when the « Template CMS » field is left empty.
     *
     * @param 'page'|'products' $kind for a taxon: its category page, or the product pages of its products
     */
    public function inheritedTemplate(mixed $resource, string $kind = 'page'): ?ResolvedCmsTemplate
    {
        return match (true) {
            $resource instanceof Product => $this->templateResolver->inheritedForProduct($resource),
            $resource instanceof Taxon && 'products' === $kind => $this->templateResolver->inheritedProductTemplateForTaxon($resource),
            $resource instanceof Taxon => $this->templateResolver->inheritedForTaxon($resource),
            default => null,
        };
    }
}
