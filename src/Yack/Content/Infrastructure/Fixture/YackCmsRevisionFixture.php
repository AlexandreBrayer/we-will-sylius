<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Fixture;

use App\Yack\Content\Application\RecordCmsRevision;
use App\Yack\Content\Domain\CmsPages;
use App\Yack\Content\Domain\CmsRevisions;
use App\Yack\Content\Domain\CmsTemplates;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;

/**
 * Starts the history of every page and template of the fixtures with its current version, without an
 * author: the history panel opens on the version of the demo, which can be restored after a test. A
 * target that already has a revision is left as is. Runs after yack_cms_page and yack_cms_template.
 */
final class YackCmsRevisionFixture extends AbstractFixture
{
    public function __construct(
        private readonly CmsPages $pages,
        private readonly CmsTemplates $templates,
        private readonly CmsRevisions $revisions,
        private readonly RecordCmsRevision $record,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getName(): string
    {
        return 'yack_cms_revision';
    }

    /**
     * @param array<string, mixed> $options
     */
    public function load(array $options): void
    {
        foreach ([...$this->pages->all(), ...$this->templates->all()] as $target) {
            if ([] === $this->revisions->summaries($target)) {
                ($this->record)($target, null);
            }
        }

        $this->entityManager->flush();
    }
}
