<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Yack\Content\Application\CreateCmsSection;
use App\Yack\Content\Domain\CmsSectionRefused;
use App\Yack\Content\Infrastructure\Security\CurrentRevisionAuthor;

/**
 * POST /admin/cms-sections: the section saved by the logged-in administrator, at his builder level
 * (BuilderLevelCheck, same rule as the pages). A refusal is a 422 on `name` or `blocks`, nothing is written.
 *
 * @implements ProcessorInterface<CmsSectionInput, CmsSectionView>
 */
final readonly class CmsSectionCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private CreateCmsSection $create,
        private BuilderLevelCheck $level,
        private CurrentRevisionAuthor $author,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CmsSectionView
    {
        try {
            $section = ($this->create)($data->name ?? '', $data->blocks ?? [], $this->level->level(), $this->author->get()?->name);
        } catch (CmsSectionRefused $refused) {
            throw CmsSectionViolation::of($refused, $data);
        }

        return CmsSectionView::of($section);
    }
}
