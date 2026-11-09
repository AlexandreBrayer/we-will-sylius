<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Application;

use App\Entity\Content\CmsPage;
use App\Yack\Content\Domain\CmsPages;

/** CmsPages over a list given in the order of all() (last modified first). */
final readonly class InMemoryCmsPages implements CmsPages
{
    /**
     * @param list<CmsPage> $pages
     */
    public function __construct(private array $pages = [])
    {
    }

    public function findPublished(string $slug): ?CmsPage
    {
        $page = $this->find($slug);

        return null !== $page && $page->isPublished() ? $page : null;
    }

    public function find(string $slug): ?CmsPage
    {
        foreach ($this->pages as $page) {
            if ($page->getSlug() === $slug) {
                return $page;
            }
        }

        return null;
    }

    public function published(): array
    {
        return array_values(array_filter($this->pages, static fn (CmsPage $page): bool => $page->isPublished()));
    }

    public function all(): array
    {
        return $this->pages;
    }
}
