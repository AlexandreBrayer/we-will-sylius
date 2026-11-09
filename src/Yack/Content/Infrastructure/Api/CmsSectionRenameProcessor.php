<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Yack\Content\Domain\CmsSectionRefused;
use App\Yack\Content\Domain\CmsSections;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PUT /admin/cms-sections/{id} {"name": "..."}: renames the section; its blocks never change (no level to
 * check). 422 on `name` when empty or too long, the section keeps its name.
 *
 * @implements ProcessorInterface<CmsSectionView, CmsSectionView>
 */
final readonly class CmsSectionRenameProcessor implements ProcessorInterface
{
    public function __construct(private CmsSections $sections)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CmsSectionView
    {
        $section = $this->sections->find($data->id) ?? throw new NotFoundHttpException('Section introuvable.');

        try {
            $section->rename($data->name);
        } catch (CmsSectionRefused $refused) {
            throw CmsSectionViolation::of($refused, $data);
        }
        $this->sections->save($section);

        return CmsSectionView::of($section);
    }
}
