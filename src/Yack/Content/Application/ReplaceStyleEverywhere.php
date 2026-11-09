<?php

declare(strict_types=1);

namespace App\Yack\Content\Application;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsTemplate;
use App\Entity\User\AdminUser;
use App\Yack\Content\Domain\CmsContentTransaction;
use App\Yack\Content\Domain\CmsEditLockState;
use App\Yack\Content\Domain\CmsPages;
use App\Yack\Content\Domain\CmsRevisionAuthor;
use App\Yack\Content\Domain\CmsRevisionTarget;
use App\Yack\Content\Domain\CmsTemplates;
use App\Yack\Content\Domain\StyleReplacement;

/**
 * Replaces a free style value by a value of the charte in every page and template (draft or published).
 * All or nothing: the blocks changed and one revision per page or template touched (RecordCmsRevision,
 * author: the administrator who confirms) are written in one transaction. The caller checks who may do
 * it (an administrator of level `libre`).
 *
 * A page or template that another administrator edits in the builder (edit lock alive, CmsEditLocking) is
 * left as it is and listed with who holds it and since when: the replacement would be lost at his next
 * save, or would make it fail. Locked by the administrator who replaces, or a lock gone silent: replaced.
 * Without an administrator (commands) nothing is left out, as for any save.
 */
final readonly class ReplaceStyleEverywhere
{
    public function __construct(
        private CmsPages $pages,
        private CmsTemplates $templates,
        private RecordCmsRevision $recordRevision,
        private CmsContentTransaction $transaction,
        private CmsEditLocking $locking,
    ) {
    }

    /**
     * What the replacement would change, and what it would leave out as locked by another administrator
     * than $admin; nothing written.
     */
    public function preview(StyleReplacement $replacement, ?AdminUser $admin): StyleReplacementResult
    {
        $result = [];
        foreach ($this->touched($replacement) as [$target, $positions]) {
            $result[] = [$target, $positions, $this->locking->blocking($target, $admin)];
        }

        return self::result($result);
    }

    /**
     * @param ?AdminUser $admin          administrator who confirms (author of the revisions), null for commands
     * @param ?int       $expectedBlocks number of blocks holding the value in the preview the administrator
     *                                   confirmed, locked ones included (StyleReplacementResult::matchedBlocks()); null: no check
     *
     * @throws StyleReplacementOutdated the content changed since the preview (nothing written)
     */
    public function __invoke(StyleReplacement $replacement, ?AdminUser $admin, ?int $expectedBlocks = null): StyleReplacementResult
    {
        $author = null === $admin ? null : CmsRevisionAuthor::of($admin);

        return $this->transaction->run(function () use ($replacement, $admin, $author, $expectedBlocks): StyleReplacementResult {
            $touched = $this->touched($replacement);
            $matched = array_sum(array_map(static fn (array $entry): int => \count($entry[1]), $touched));
            if (null !== $expectedBlocks && $matched !== $expectedBlocks) {
                throw new StyleReplacementOutdated($expectedBlocks, $matched);
            }

            $result = [];
            foreach ($touched as [$target, $positions]) {
                // Read in the transaction, its row held until the commit: a take over waits for the replacement.
                $lock = $this->locking->blockingForWrite($target, $admin);
                if (null === $lock) {
                    $target->setBlocks($replacement->apply($target->getBlocks()));
                    ($this->recordRevision)($target, $author);
                }
                $result[] = [$target, $positions, $lock];
            }

            return self::result($result);
        });
    }

    /**
     * Pages (last modified first) then templates holding the value, with the positions of their blocks.
     *
     * @return list<array{CmsPage|CmsTemplate, list<int>}>
     */
    private function touched(StyleReplacement $replacement): array
    {
        $touched = [];
        foreach ([...$this->pages->all(), ...$this->templates->all()] as $target) {
            $positions = $replacement->matches($target->getBlocks());
            if ([] !== $positions) {
                $touched[] = [$target, $positions];
            }
        }

        return $touched;
    }

    /**
     * @param list<array{CmsPage|CmsTemplate, list<int>, ?CmsEditLockState}> $touched with the lock that leaves the target out, if any
     */
    private static function result(array $touched): StyleReplacementResult
    {
        $targets = [];
        $skipped = [];
        foreach ($touched as [$target, $positions, $lock]) {
            $entry = $target instanceof CmsPage
                ? ['target' => CmsRevisionTarget::Page, 'ref' => $target->getSlug(), 'label' => $target->getTitle(), 'blocks' => $positions]
                : ['target' => CmsRevisionTarget::Template, 'ref' => (string) $target->getId(), 'label' => $target->getName(), 'blocks' => $positions];
            if (null === $lock) {
                $targets[] = $entry;
            } else {
                $skipped[] = $entry + ['holder' => $lock->holder->name, 'since' => $lock->since];
            }
        }

        return new StyleReplacementResult($targets, $skipped);
    }
}
