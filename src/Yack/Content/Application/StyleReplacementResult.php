<?php

declare(strict_types=1);

namespace App\Yack\Content\Application;

use App\Yack\Content\Domain\CmsRevisionTarget;

/**
 * Pages and templates a bulk style replacement touches (ReplaceStyleEverywhere), with where their blocks are
 * (StyleReplacement::matches()), and those it leaves out because another administrator edits them in the builder (edit lock, D13):
 * who holds the lock and since when.
 */
final readonly class StyleReplacementResult
{
    /**
     * @param list<array{target: CmsRevisionTarget, ref: string, label: string, blocks: list<string>}>                                           $targets replaced (or to replace); ref: slug of the page, identifier of the template; blocks: where they are (« Bloc n°2 », « Section 1 › bloc 2 »)
     * @param list<array{target: CmsRevisionTarget, ref: string, label: string, blocks: list<string>, holder: string, since: \DateTimeImmutable}> $skipped holding the value but locked by another administrator: not changed
     */
    public function __construct(public array $targets, public array $skipped = [])
    {
    }

    /** Number of blocks changed (or to change). */
    public function blocks(): int
    {
        return self::sum($this->targets);
    }

    public function pages(): int
    {
        return self::count($this->targets, CmsRevisionTarget::Page);
    }

    public function templates(): int
    {
        return self::count($this->targets, CmsRevisionTarget::Template);
    }

    /** Number of blocks left as they are because their page or template is locked by another administrator. */
    public function skippedBlocks(): int
    {
        return self::sum($this->skipped);
    }

    public function skippedPages(): int
    {
        return self::count($this->skipped, CmsRevisionTarget::Page);
    }

    public function skippedTemplates(): int
    {
        return self::count($this->skipped, CmsRevisionTarget::Template);
    }

    /** Number of blocks holding the value, locked or not: what the confirmation checks against the preview. */
    public function matchedBlocks(): int
    {
        return $this->blocks() + $this->skippedBlocks();
    }

    /** No block holds the value. */
    public function isEmpty(): bool
    {
        return [] === $this->targets && [] === $this->skipped;
    }

    /**
     * @param list<array{target: CmsRevisionTarget, blocks: list<string>, ...}> $targets
     */
    private static function sum(array $targets): int
    {
        return array_sum(array_map(static fn (array $target): int => \count($target['blocks']), $targets));
    }

    /**
     * @param list<array{target: CmsRevisionTarget, blocks: list<string>, ...}> $targets
     */
    private static function count(array $targets, CmsRevisionTarget $kind): int
    {
        return \count(array_filter($targets, static fn (array $target): bool => $kind === $target['target']));
    }
}
