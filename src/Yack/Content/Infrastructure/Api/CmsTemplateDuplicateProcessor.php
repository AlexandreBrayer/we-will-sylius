<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Content\CmsTemplate;
use App\Yack\Content\Domain\CmsTemplates;
use Doctrine\ORM\EntityManagerInterface;

/**
 * POST /admin/cms-templates/{id}/duplicate (no body): draft copy « Copie de … », same type and
 * content, not default, no assignment.
 *
 * @implements ProcessorInterface<CmsTemplate, CmsTemplate>
 */
final readonly class CmsTemplateDuplicateProcessor implements ProcessorInterface
{
    public function __construct(
        private CmsTemplates $templates,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CmsTemplate
    {
        $copy = $data->duplicate(mb_substr('Copie de ' . $data->getName(), 0, 255));
        $this->templates->add($copy);
        $this->entityManager->flush();

        return $copy;
    }
}
