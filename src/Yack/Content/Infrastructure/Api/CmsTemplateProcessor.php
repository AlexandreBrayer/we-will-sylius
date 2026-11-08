<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Content\CmsTemplate;
use App\Yack\Content\Domain\CmsTemplates;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Admin writes on CMS templates: builder level of the author, then one default per type.
 *  - the blocks follow the builder level of the author (BuilderLevelCheck), compared with the stored
 *    version (`previous_data`, see CmsPageProcessor), before any other template is touched;
 *  - saving a template as default removes the flag from the other templates of its type;
 *  - the current default can neither lose its flag (another template must be made default instead)
 *    nor be deleted (409), so a published default always exists once one has been set.
 *
 * @implements ProcessorInterface<CmsTemplate, CmsTemplate|null>
 */
final readonly class CmsTemplateProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<CmsTemplate, CmsTemplate> $persistProcessor
     * @param ProcessorInterface<CmsTemplate, null>        $removeProcessor
     */
    public function __construct(
        private CmsTemplates $templates,
        private BuilderLevelCheck $level,
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?CmsTemplate
    {
        if ($operation instanceof DeleteOperationInterface) {
            if ($data->isDefault()) {
                throw new ConflictHttpException('Le template par défaut ne peut pas être supprimé : définissez d\'abord un autre template par défaut.');
            }

            return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        }

        $previous = $context['previous_data'] ?? null;
        $this->level->assertAllowed($data, $data->getBlocks(), $previous instanceof CmsTemplate ? $previous->getBlocks() : []);

        if ($previous instanceof CmsTemplate && $previous->isDefault() && !$data->isDefault()) {
            throw new ValidationException(new ConstraintViolationList([new ConstraintViolation(
                'Ce template est le template par défaut : pour le remplacer, définissez un autre template par défaut.',
                null,
                [],
                $data,
                'isDefault',
                false,
            )]));
        }

        if ($data->isDefault()) {
            foreach ($this->templates->all() as $template) {
                if ($template !== $data && $template->getType() === $data->getType() && $template->isDefault()) {
                    $template->setDefault(false);
                }
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
