<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/** Writes of CMS content done all at once: every change made by the work, or none when it fails. */
interface CmsContentTransaction
{
    /**
     * Runs $work, then writes the pages, templates and revisions it changed in one transaction; when
     * $work or the write throws, nothing is written and the exception goes on.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function run(callable $work): mixed;
}
