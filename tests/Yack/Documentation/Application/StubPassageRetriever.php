<?php

declare(strict_types=1);

namespace App\Tests\Yack\Documentation\Application;

use App\Yack\Documentation\Domain\Passage;
use App\Yack\Documentation\Domain\PassageRetriever;
use App\Yack\Documentation\Domain\Question;

/** Hand-written fake: gives the passages it was built with, records the calls. */
final class StubPassageRetriever implements PassageRetriever
{
    /** @var list<array{question: Question, limit: int}> */
    public array $calls = [];

    /** @param list<Passage> $passages */
    public function __construct(private readonly array $passages)
    {
    }

    public function retrieve(Question $question, int $limit): array
    {
        $this->calls[] = ['question' => $question, 'limit' => $limit];

        return \array_slice($this->passages, 0, $limit);
    }
}
