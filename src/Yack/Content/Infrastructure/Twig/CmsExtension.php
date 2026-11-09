<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Twig;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsTemplate;
use App\Entity\Product\Product;
use App\Entity\Taxonomy\Taxon;
use App\Yack\Content\Application\CmsTemplateResolver;
use App\Yack\Content\Domain\ResolvedCmsTemplate;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Back-office (templates/admin/yack/cms_page/, cms_template/, product and taxon form sections):
 * links to the SvelteKit front, which renders the pages and hosts the builder, and effective
 * CMS templates of the edited product / taxon. The admin and the front share one origin behind
 * nginx: links are paths. The builder and the draft previews open through the admin
 * (BuilderAccessController), so the logged-in administrator never types a second password.
 */
final class CmsExtension extends AbstractExtension
{
    public function __construct(
        private readonly UrlGeneratorInterface $urls,
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

    /** Page of the shop (`/` for the home page); `$preview`: its draft, through the builder session. */
    public function url(CmsPage|string $page, bool $preview = false): string
    {
        $slug = $page instanceof CmsPage ? $page->getSlug() : $page;
        $path = 'accueil' === $slug ? '/' : '/' . rawurlencode($slug);

        return $preview ? $this->throughAdmin($path . '?apercu=1') : $path;
    }

    /** Builder: page list (null) or page editor. */
    public function builderUrl(?string $slug = null): string
    {
        return $this->throughAdmin('/cms-admin/builder' . (null === $slug ? '' : '/' . rawurlencode($slug)));
    }

    /** Builder: template list (null) or template editor. */
    public function templateBuilderUrl(CmsTemplate|int|null $template = null): string
    {
        $id = $template instanceof CmsTemplate ? $template->getId() : $template;

        return $this->throughAdmin('/cms-admin/builder/templates' . (null === $id ? '' : '/' . $id));
    }

    private function throughAdmin(string $path): string
    {
        return $this->urls->generate('app_yack_admin_builder', ['vers' => $path]);
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
