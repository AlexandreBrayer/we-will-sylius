<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Infrastructure\Assistant;

use App\Yack\Documentation\Domain\Answer;
use App\Yack\Documentation\Domain\DocumentationAnswerer;
use App\Yack\Documentation\Domain\Question;

/**
 * Demonstration adapter (YACK_AI_ADAPTER=fake, default): extractive and deterministic, no model.
 * The answer is the best retrieved passage, word for word, with its notice and page.
 */
final class FakeDocumentationAnswerer implements DocumentationAnswerer
{
    public function answer(Question $question, array $passages): Answer
    {
        $best = $passages[0] ?? null;
        if (null === $best) {
            return Answer::notFound(simulated: true);
        }

        return Answer::cited($best->text, [$best->citation()], simulated: true);
    }
}
