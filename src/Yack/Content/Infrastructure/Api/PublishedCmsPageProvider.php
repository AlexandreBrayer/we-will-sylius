<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Content\CmsPage;
use App\Yack\Content\Domain\CmsPages;

/**
 * Shop API: only published pages exist (drafts answer 404).
 *
 * @implements ProviderInterface<CmsPage>
 */
final readonly class PublishedCmsPageProvider implements ProviderInterface
{
    public function __construct(private CmsPages $pages)
    {
    }

    /**
     * @return list<CmsPage>|CmsPage|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|CmsPage|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            return $this->pages->published();
        }

        $slug = $uriVariables['slug'] ?? null;

        return is_string($slug) ? $this->pages->findPublished($slug) : null;
    }
}
