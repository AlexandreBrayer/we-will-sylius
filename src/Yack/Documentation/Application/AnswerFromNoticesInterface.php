<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Application;

use App\Yack\Common\Domain\AiUnavailable;
use App\Yack\Documentation\Domain\Answer;
use App\Yack\Documentation\Domain\Notice;
use App\Yack\Documentation\Domain\Question;

/**
 * Exposed to other contexts (ADR 008): a question answered from the notices given by the caller, not from
 * the documentation corpus. Same steps as AskDocumentation: lexical retrieval (RetrievePassages) over these
 * notices only, the answerer reads the retrieved passages only, sources restricted to them.
 */
interface AnswerFromNoticesInterface
{
    /**
     * No notice, or nothing relevant in them: Answer::notFound(), no model called.
     *
     * @param list<Notice> $notices
     * @param list<string> $rewordings other wordings of the same question, used for the retrieval only (business
     *                                 terms translated for a notice written in another language, for example);
     *                                 the answerer always receives `$question`
     *
     * @throws AiUnavailable when the language model cannot answer now
     */
    public function answer(Question $question, array $notices, array $rewordings = []): Answer;
}
