<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Twig;

use App\Entity\Content\CmsPage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Back-office page list (templates/admin/yack/cms_page/): links to the SvelteKit front,
 * which renders the pages and hosts the builder.
 */
final class CmsExtension extends AbstractExtension
{
    public function __construct(
        #[Autowire(param: 'app.yack.front_url')]
        private readonly string $frontUrl,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('yack_cms_url', $this->url(...)),
            new TwigFunction('yack_cms_builder_url', $this->builderUrl(...)),
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
}
