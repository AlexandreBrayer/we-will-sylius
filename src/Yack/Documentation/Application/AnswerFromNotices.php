<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Application;

use App\Yack\Documentation\Domain\Answer;
use App\Yack\Documentation\Domain\DocumentationAnswerer;
use App\Yack\Documentation\Domain\DocumentationCorpus;
use App\Yack\Documentation\Domain\InvalidQuestion;
use App\Yack\Documentation\Domain\Notice;
use App\Yack\Documentation\Domain\Passage;
use App\Yack\Documentation\Domain\Question;

/**
 * A question answered from the notices of the caller: RetrievePassages over an in-memory corpus of these
 * notices, then DocumentationAnswerer on the retrieved passages, then Answer::restrictedTo.
 *
 * Rewordings: each wording (the question first) is retrieved on its own, with the same relevance rule; the
 * passages are merged in turn (first of each wording, then second…), without duplicate, `$limit` at most.
 * A rewording that is not a valid question (too short, too long) is ignored.
 */
final readonly class AnswerFromNotices implements AnswerFromNoticesInterface
{
    public function __construct(
        private DocumentationAnswerer $answerer,
        private int $limit = RetrievePassages::DEFAULT_LIMIT,
    ) {
    }

    public function answer(Question $question, array $notices, array $rewordings = []): Answer
    {
        $passages = [] === $notices ? [] : $this->retrieve($question, $notices, $rewordings);
        if ([] === $passages) {
            return Answer::notFound();
        }

        return $this->answerer->answer($question, $passages)->restrictedTo($passages);
    }

    /**
     * @param list<Notice> $notices
     * @param list<string> $rewordings
     *
     * @return list<Passage>
     */
    private function retrieve(Question $question, array $notices, array $rewordings): array
    {
        $retriever = new RetrievePassages(new class($notices) implements DocumentationCorpus {
            /** @param list<Notice> $notices */
            public function __construct(private readonly array $notices)
            {
            }

            public function notices(): array
            {
                return $this->notices;
            }
        });

        $lists = [$retriever->retrieve($question, $this->limit)];
        foreach ($rewordings as $text) {
            try {
                $reworded = Question::ask($text, $question->productCode);
            } catch (InvalidQuestion) {
                continue;
            }
            if ($reworded->text !== $question->text) {
                $lists[] = $retriever->retrieve($reworded, $this->limit);
            }
        }

        $merged = [];
        for ($rank = 0; \count($merged) < $this->limit && $rank < $this->limit; ++$rank) {
            foreach ($lists as $list) {
                $passage = $list[$rank] ?? null;
                if (null !== $passage && \count($merged) < $this->limit) {
                    $merged[spl_object_id($passage)] = $passage;
                }
            }
        }

        return array_values($merged);
    }
}
