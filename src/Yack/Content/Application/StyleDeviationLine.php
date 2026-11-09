<?php

declare(strict_types=1);

namespace App\Yack\Content\Application;

use App\Yack\Content\Domain\BlockStyle;
use App\Yack\Content\Domain\CmsRevisionAuthor;
use App\Yack\Content\Domain\CmsRevisionTarget;

/**
 * Line of the report of the charte deviations (StyleDeviationReport): where the free value is (page or
 * template, block), which one, and who saved the target last. The last save comes from the newest
 * revision of the target; `lastSavedAt` is null when the target has no revision.
 */
final readonly class StyleDeviationLine
{
    /**
     * @param string                  $targetKey     `page:<slug>` or `template:<id>`, the value of the target filter
     * @param string                  $targetRef     slug of the page, identifier of the template
     * @param string                  $targetLabel   title of the page, name of the template
     * @param ?string                 $templateType  label of the template type (« Produit », « Catégorie »), null for a page
     * @param int                     $block         position of the block in the content, from 1
     * @param ?CmsRevisionAuthor      $lastAuthor    author of the newest revision, null for a revision without author or no revision
     * @param ?\DateTimeImmutable     $lastSavedAt   date of the newest revision, null without revision
     */
    public function __construct(
        public CmsRevisionTarget $target,
        public string $targetKey,
        public string $targetRef,
        public string $targetLabel,
        public ?string $templateType,
        public int $block,
        public string $blockType,
        public string $blockLabel,
        public string $property,
        public string $value,
        public ?CmsRevisionAuthor $lastAuthor,
        public ?\DateTimeImmutable $lastSavedAt,
    ) {
    }

    public function propertyLabel(): string
    {
        return BlockStyle::label($this->property);
    }

    /** The value is a colour (#rrggbb, checked here): the report shows it as a swatch. */
    public function color(): ?string
    {
        return BlockStyle::KIND_COLOR === BlockStyle::kind($this->property) && 1 === preg_match(BlockStyle::COLOR_PATTERN, $this->value)
            ? strtolower($this->value)
            : null;
    }

    /** Identifier of the template, null for a page. */
    public function templateId(): ?int
    {
        return CmsRevisionTarget::Template === $this->target ? (int) $this->targetRef : null;
    }

    public function hasRevision(): bool
    {
        return null !== $this->lastSavedAt;
    }
}
