<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Yack\Content\Domain\CmsPages;
use App\Yack\Content\Domain\CmsRevisions;
use App\Yack\Content\Domain\CmsRevisionSummary;
use App\Yack\Content\Domain\CmsTemplates;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Revisions of a page (`slug`) or of a template (`id` of a collection), or one revision (`id` of an item).
 *
 * @implements ProviderInterface<CmsRevisionView>
 */
final readonly class CmsRevisionProvider implements ProviderInterface
{
    public function __construct(
        private CmsRevisions $revisions,
        private CmsPages $pages,
        private CmsTemplates $templates,
    ) {
    }

    /**
     * @return CmsRevisionView|list<CmsRevisionView>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CmsRevisionView|array|null
    {
        $id = $uriVariables['id'] ?? null;
        if (!$operation instanceof CollectionOperationInterface) {
            $revision = is_numeric($id) ? $this->revisions->find((int) $id) : null;

            return null === $revision ? null : CmsRevisionView::of($revision);
        }

        $slug = $uriVariables['slug'] ?? null;
        $target = match (true) {
            \is_string($slug) => $this->pages->find($slug),
            is_numeric($id) => $this->templates->find((int) $id),
            default => null,
        };
        if (null === $target) {
            throw new NotFoundHttpException(\is_string($slug) ? 'Page introuvable.' : 'Template introuvable.');
        }

        return array_map(static fn (CmsRevisionSummary $summary): CmsRevisionView => CmsRevisionView::summary($summary, $target), $this->revisions->summaries($target));
    }
}
