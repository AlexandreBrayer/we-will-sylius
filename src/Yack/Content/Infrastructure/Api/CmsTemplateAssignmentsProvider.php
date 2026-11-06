<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Content\CmsTemplate;
use App\Yack\Content\Domain\CmsTemplateAssignments;
use App\Yack\Content\Domain\CmsTemplates;

/**
 * @implements ProviderInterface<CmsTemplateAssignmentsView>
 */
final readonly class CmsTemplateAssignmentsProvider implements ProviderInterface
{
    public function __construct(
        private CmsTemplates $templates,
        private CmsTemplateAssignments $assignments,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?CmsTemplateAssignmentsView
    {
        $id = $uriVariables['id'] ?? null;
        $template = is_numeric($id) ? $this->templates->find((int) $id) : null;

        return null === $template ? null : $this->view($template);
    }

    public function view(CmsTemplate $template): CmsTemplateAssignmentsView
    {
        $assigned = $this->assignments->of($template);

        return new CmsTemplateAssignmentsView(
            (int) $template->getId(),
            $template->getType()->value,
            array_column($assigned['products'], 'code'),
            array_column($assigned['taxons'], 'code'),
            $assigned['products'],
            $assigned['taxons'],
        );
    }
}
