<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Domain;

use App\Yack\Common\Domain\AiUnavailable;

/**
 * Port: writes the answer to a question from the retrieved passages only (never from anything
 * else). Every source of the answer must be one of `$passages`.
 */
interface DocumentationAnswerer
{
    /**
     * @param non-empty-list<Passage> $passages
     *
     * @throws AiUnavailable when the language model cannot answer now
     */
    public function answer(Question $question, array $passages): Answer;
}
