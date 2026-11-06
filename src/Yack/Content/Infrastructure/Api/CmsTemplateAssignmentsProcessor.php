<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Yack\Content\Domain\CmsTemplateAssignments;
use App\Yack\Content\Domain\CmsTemplates;
use App\Yack\Content\Domain\InvalidCmsTemplateAssignment;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @implements ProcessorInterface<CmsTemplateAssignmentsView, CmsTemplateAssignmentsView>
 */
final readonly class CmsTemplateAssignmentsProcessor implements ProcessorInterface
{
    public function __construct(
        private CmsTemplates $templates,
        private CmsTemplateAssignments $assignments,
        private CmsTemplateAssignmentsProvider $provider,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CmsTemplateAssignmentsView
    {
        $template = $this->templates->find($data->id) ?? throw new NotFoundHttpException('Template introuvable.');

        try {
            $this->assignments->replace($template, $data->productCodes, $data->taxonCodes);
        } catch (InvalidCmsTemplateAssignment $exception) {
            throw new ValidationException(new ConstraintViolationList([
                new ConstraintViolation($exception->getMessage(), null, [], $data, $exception->field, null),
            ]));
        }

        return $this->provider->view($template);
    }
}
