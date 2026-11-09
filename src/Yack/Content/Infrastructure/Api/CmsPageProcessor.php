<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Content\CmsPage;
use App\Yack\Content\Application\RecordCmsRevision;
use App\Yack\Content\Infrastructure\Security\CurrentRevisionAuthor;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Admin writes on CMS pages (POST, PUT): the builder level of the author applies to the blocks
 * (BuilderLevelCheck) before the Doctrine write, so a refused page is not flushed. An accepted page
 * gets a revision (RecordCmsRevision), written by the same flush as the page: one transaction.
 *
 * Stored version: `previous_data`, the clone of the entity taken by API Platform when it reads it,
 * before the request body is written into it (Sylius sets `standard_put: false`: PUT updates the
 * managed entity in place). POST has none: every free value is new.
 *
 * Edit lock (D13, CmsEditLockGuard): a page whose lock another administrator holds is not saved (409).
 * The check, the revision and the write run in the transaction that holds the lock row, so a take over
 * and a save never cross.
 *
 * @implements ProcessorInterface<CmsPage, CmsPage>
 */
final readonly class CmsPageProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<CmsPage, CmsPage> $persistProcessor
     */
    public function __construct(
        private CmsEditLockGuard $lock,
        private BuilderLevelCheck $level,
        private RecordCmsRevision $revision,
        private CurrentRevisionAuthor $author,
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CmsPage
    {
        $previous = $context['previous_data'] ?? null;

        return $this->lock->save($data, function () use ($data, $previous, $operation, $uriVariables, $context): CmsPage {
            $this->level->assertAllowed($data, $data->getBlocks(), $previous instanceof CmsPage ? $previous->getBlocks() : []);
            ($this->revision)($data, $this->author->get());

            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        });
    }
}
