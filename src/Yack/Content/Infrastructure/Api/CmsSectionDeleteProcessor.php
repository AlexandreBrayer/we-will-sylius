<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Yack\Content\Domain\CmsSections;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * DELETE /admin/cms-sections/{id}: 204. The pages and templates where it was inserted keep their blocks
 * (copies, no link to the section).
 *
 * @implements ProcessorInterface<CmsSectionView, null>
 */
final readonly class CmsSectionDeleteProcessor implements ProcessorInterface
{
    public function __construct(private CmsSections $sections)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $section = $this->sections->find($data->id) ?? throw new NotFoundHttpException('Section introuvable.');
        $this->sections->remove($section);

        return null;
    }
}
