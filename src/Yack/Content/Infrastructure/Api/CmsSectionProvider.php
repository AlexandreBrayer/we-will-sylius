<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Yack\Content\Domain\CmsSections;

/**
 * Sections of the builder (CmsSectionView): all of them, or one by its id (null, hence 404, when unknown).
 *
 * @implements ProviderInterface<CmsSectionView>
 */
final readonly class CmsSectionProvider implements ProviderInterface
{
    public function __construct(private CmsSections $sections)
    {
    }

    /**
     * @return CmsSectionView|list<CmsSectionView>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CmsSectionView|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            return array_map(CmsSectionView::of(...), $this->sections->all());
        }

        $id = $uriVariables['id'] ?? null;
        $section = is_numeric($id) ? $this->sections->find((int) $id) : null;

        return null === $section ? null : CmsSectionView::of($section);
    }
}
