<?php

declare(strict_types=1);

namespace App\Tests\Yack\Documentation\Application;

use App\Yack\Documentation\Domain\Answer;
use App\Yack\Documentation\Domain\DocumentationAnswerer;
use App\Yack\Documentation\Domain\Passage;
use App\Yack\Documentation\Domain\Question;

/** Hand-written fake: records what it receives and gives the prepared answer (or throws). */
final class RecordingDocumentationAnswerer implements DocumentationAnswerer
{
    /** @var list<list<Passage>> */
    public array $received = [];

    public function __construct(private readonly Answer|\Throwable $result)
    {
    }

    public function answer(Question $question, array $passages): Answer
    {
        $this->received[] = $passages;
        if ($this->result instanceof \Throwable) {
            throw $this->result;
        }

        return $this->result;
    }
}
