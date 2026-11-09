<?php

declare(strict_types=1);

namespace App\Yack\Content\Application;

use App\Yack\Content\Domain\CmsRevisionTarget;

/** Pages and templates a bulk style replacement touches (ReplaceStyleEverywhere), with the positions of their blocks. */
final readonly class StyleReplacementResult
{
    /**
     * @param list<array{target: CmsRevisionTarget, ref: string, label: string, blocks: list<int>}> $targets ref: slug of the page, identifier of the template; blocks: positions from 1
     */
    public function __construct(public array $targets)
    {
    }

    /** Number of blocks changed (or to change). */
    public function blocks(): int
    {
        return array_sum(array_map(static fn (array $target): int => \count($target['blocks']), $this->targets));
    }

    public function pages(): int
    {
        return $this->count(CmsRevisionTarget::Page);
    }

    public function templates(): int
    {
        return $this->count(CmsRevisionTarget::Template);
    }

    public function isEmpty(): bool
    {
        return [] === $this->targets;
    }

    private function count(CmsRevisionTarget $kind): int
    {
        return \count(array_filter($this->targets, static fn (array $target): bool => $kind === $target['target']));
    }
}
