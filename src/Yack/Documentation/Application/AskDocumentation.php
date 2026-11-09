<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Application;

use App\Yack\Common\Domain\AiUnavailable;
use App\Yack\Documentation\Domain\Answer;
use App\Yack\Documentation\Domain\DocumentationAnswerer;
use App\Yack\Documentation\Domain\PassageRetriever;
use App\Yack\Documentation\Domain\Question;

/**
 * A question to the documentation: retrieve the passages, then let the answerer write from them.
 *
 * - nothing relevant: "Je ne trouve pas la réponse dans la documentation", no model called;
 * - only the retrieved passages are given to the answerer, never the whole corpus;
 * - the answer keeps only sources among those passages (Answer::restrictedTo), whatever the adapter.
 */
final readonly class AskDocumentation
{
    public function __construct(
        private PassageRetriever $retriever,
        private DocumentationAnswerer $answerer,
        private int $limit = RetrievePassages::DEFAULT_LIMIT,
    ) {
    }

    /** @throws AiUnavailable */
    public function __invoke(Question $question): Answer
    {
        $passages = $this->retriever->retrieve($question, $this->limit);
        if ([] === $passages) {
            return Answer::notFound();
        }

        return $this->answerer->answer($question, $passages)->restrictedTo($passages);
    }
}
