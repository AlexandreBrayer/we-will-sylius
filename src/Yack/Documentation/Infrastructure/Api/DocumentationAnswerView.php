<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Infrastructure\Api;

use App\Yack\Documentation\Domain\Answer;
use Symfony\Component\Serializer\Attribute\Groups;

/** Answer of POST /shop/yack/documentation-questions; `sources` empty only for "je ne trouve pas". */
final readonly class DocumentationAnswerView
{
    /** @param list<array{source: string, page: int}> $sources */
    public function __construct(
        #[Groups(['documentation_question:item'])]
        public string $answer,
        #[Groups(['documentation_question:item'])]
        public array $sources,
        /** True when written by the demonstration adapter (YACK_AI_ADAPTER=fake). */
        #[Groups(['documentation_question:item'])]
        public bool $simulated,
    ) {
    }

    public static function fromAnswer(Answer $answer): self
    {
        $data = $answer->toArray();

        return new self($data['answer'], $data['sources'], $data['simulated']);
    }
}
