<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Content\CmsPage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Admin writes on CMS pages (POST, PUT): the builder level of the author applies to the blocks
 * (BuilderLevelCheck) before the Doctrine write, so a refused page is not flushed.
 *
 * Stored version: `previous_data`, the clone of the entity taken by API Platform when it reads it,
 * before the request body is written into it (Sylius sets `standard_put: false`: PUT updates the
 * managed entity in place). POST has none: every free value is new.
 *
 * @implements ProcessorInterface<CmsPage, CmsPage>
 */
final readonly class CmsPageProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<CmsPage, CmsPage> $persistProcessor
     */
    public function __construct(
        private BuilderLevelCheck $level,
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CmsPage
    {
        $previous = $context['previous_data'] ?? null;
        $this->level->assertAllowed($data, $data->getBlocks(), $previous instanceof CmsPage ? $previous->getBlocks() : []);

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
