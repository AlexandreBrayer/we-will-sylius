<?php

declare(strict_types=1);

namespace App\Yack\Content\Application;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsRevision;
use App\Entity\Content\CmsTemplate;
use App\Yack\Content\Domain\CmsRevisionAuthor;
use App\Yack\Content\Domain\CmsRevisions;

/**
 * Records the version of a page or a template that is about to be saved, and keeps the KEEP newest of
 * the target. To call once every rule has passed, just before the flush that writes the content: that
 * flush writes the revision and removes the oldest one in the same transaction, so a refused or failed
 * save leaves no revision. Used by every write of CMS content (API processors, bulk changes).
 */
final readonly class RecordCmsRevision
{
    public const KEEP = 50;

    public function __construct(private CmsRevisions $revisions)
    {
    }

    /**
     * @param ?CmsRevisionAuthor $author administrator who saves, null for fixtures and commands
     */
    public function __invoke(CmsPage|CmsTemplate $target, ?CmsRevisionAuthor $author): CmsRevision
    {
        $revision = $target instanceof CmsPage ? CmsRevision::ofPage($target, $author) : CmsRevision::ofTemplate($target, $author);
        $this->revisions->add($revision);
        if (null !== $target->getId()) {
            $this->revisions->removeOldest($target, self::KEEP - 1);
        }

        return $revision;
    }
}
