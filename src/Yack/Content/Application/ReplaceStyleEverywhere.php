<?php

declare(strict_types=1);

namespace App\Yack\Content\Application;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsTemplate;
use App\Yack\Content\Domain\CmsContentTransaction;
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
 */
final readonly class ReplaceStyleEverywhere
{
    public function __construct(
        private CmsPages $pages,
        private CmsTemplates $templates,
        private RecordCmsRevision $recordRevision,
        private CmsContentTransaction $transaction,
    ) {
    }

    /** What the replacement would change, nothing written. */
    public function preview(StyleReplacement $replacement): StyleReplacementResult
    {
        return self::result($this->touched($replacement));
    }

    /**
     * @param ?int $expectedBlocks number of blocks of the preview the administrator confirmed; null: no check
     *
     * @throws StyleReplacementOutdated the content changed since the preview (nothing written)
     */
    public function __invoke(StyleReplacement $replacement, ?CmsRevisionAuthor $author, ?int $expectedBlocks = null): StyleReplacementResult
    {
        return $this->transaction->run(function () use ($replacement, $author, $expectedBlocks): StyleReplacementResult {
            $touched = $this->touched($replacement);
            $result = self::result($touched);
            if (null !== $expectedBlocks && $result->blocks() !== $expectedBlocks) {
                throw new StyleReplacementOutdated($expectedBlocks, $result->blocks());
            }

            foreach ($touched as [$target]) {
                $target->setBlocks($replacement->apply($target->getBlocks()));
                ($this->recordRevision)($target, $author);
            }

            return $result;
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
     * @param list<array{CmsPage|CmsTemplate, list<int>}> $touched
     */
    private static function result(array $touched): StyleReplacementResult
    {
        return new StyleReplacementResult(array_map(static fn (array $entry): array => $entry[0] instanceof CmsPage
            ? ['target' => CmsRevisionTarget::Page, 'ref' => $entry[0]->getSlug(), 'label' => $entry[0]->getTitle(), 'blocks' => $entry[1]]
            : ['target' => CmsRevisionTarget::Template, 'ref' => (string) $entry[0]->getId(), 'label' => $entry[0]->getName(), 'blocks' => $entry[1]], $touched));
    }
}
