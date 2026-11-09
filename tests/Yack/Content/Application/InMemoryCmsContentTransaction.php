<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Application;

use App\Yack\Content\Domain\CmsContentTransaction;

/**
 * CmsContentTransaction as Doctrine runs it for the revisions: the work, then one flush of what it left
 * pending; when the work throws, the pending revisions are dropped (rollback) and the exception goes on.
 */
final class InMemoryCmsContentTransaction implements CmsContentTransaction
{
    public int $runs = 0;

    public int $commits = 0;

    public int $rollbacks = 0;

    public function __construct(private readonly InMemoryCmsRevisions $revisions)
    {
    }

    public function run(callable $work): mixed
    {
        ++$this->runs;

        try {
            $result = $work();
        } catch (\Throwable $exception) {
            ++$this->rollbacks;
            $this->revisions->pending = [];

            throw $exception;
        }
        $this->revisions->flush();
        ++$this->commits;

        return $result;
    }
}
