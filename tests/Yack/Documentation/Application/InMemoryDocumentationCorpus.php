<?php

declare(strict_types=1);

namespace App\Tests\Yack\Documentation\Application;

use App\Yack\Documentation\Domain\DocumentationCorpus;
use App\Yack\Documentation\Domain\Notice;

/** Hand-written fake of the corpus port; counts the reads. */
final class InMemoryDocumentationCorpus implements DocumentationCorpus
{
    public int $reads = 0;

    /** @param list<Notice> $notices */
    public function __construct(private readonly array $notices)
    {
    }

    public function notices(): array
    {
        ++$this->reads;

        return $this->notices;
    }
}
