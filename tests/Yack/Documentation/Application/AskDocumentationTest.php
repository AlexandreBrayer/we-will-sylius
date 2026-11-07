<?php

declare(strict_types=1);

namespace App\Tests\Yack\Documentation\Application;

use App\Yack\Common\Domain\AiUnavailable;
use App\Yack\Documentation\Application\AskDocumentation;
use App\Yack\Documentation\Application\RetrievePassages;
use App\Yack\Documentation\Domain\Answer;
use App\Yack\Documentation\Domain\Citation;
use App\Yack\Documentation\Domain\Passage;
use App\Yack\Documentation\Domain\Question;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AskDocumentation::class)]
final class AskDocumentationTest extends TestCase
{
    public function testNothingRelevantAnswersNotFoundWithoutCallingTheAnswerer(): void
    {
        $answerer = new RecordingDocumentationAnswerer(Answer::cited('ne doit pas servir', [new Citation('N', 1)]));

        $answer = (new AskDocumentation(new StubPassageRetriever([]), $answerer))(Question::ask('Capitale du Portugal ?'));

        $this->assertSame(Answer::NOT_FOUND, $answer->text);
        $this->assertFalse($answer->isFound());
        $this->assertFalse($answer->simulated);
        $this->assertSame([], $answerer->received);
    }

    public function testOnlyTheRetrievedPassagesAreGivenToTheAnswerer(): void
    {
        $passages = [new Passage('Notice PAC', 12, 'Liaison : 25 m.'), new Passage('Notice PAC', 13, 'Tubes Ø 32 mm.')];
        $retriever = new StubPassageRetriever($passages);
        $answerer = new RecordingDocumentationAnswerer(Answer::cited('25 m.', [new Citation('Notice PAC', 12)], simulated: true));

        $answer = (new AskDocumentation($retriever, $answerer))(Question::ask('Longueur de liaison ?', 'YK-PAE-M08'));

        $this->assertSame([$passages], $answerer->received);
        $this->assertSame(RetrievePassages::DEFAULT_LIMIT, $retriever->calls[0]['limit']);
        $this->assertSame('YK-PAE-M08', $retriever->calls[0]['question']->productCode);
        $this->assertSame(['answer' => '25 m.', 'sources' => [['source' => 'Notice PAC', 'page' => 12]], 'simulated' => true], $answer->toArray());
    }

    public function testTheLimitIsPassedToTheRetriever(): void
    {
        $retriever = new StubPassageRetriever([new Passage('N', 1, 'a'), new Passage('N', 2, 'b')]);
        $answerer = new RecordingDocumentationAnswerer(Answer::cited('a', [new Citation('N', 1)]));

        (new AskDocumentation($retriever, $answerer, 1))(Question::ask('question'));

        $this->assertSame(1, $retriever->calls[0]['limit']);
        $this->assertCount(1, $answerer->received[0]);
    }

    public function testASourceOutsideThePassagesIsRemoved(): void
    {
        $answerer = new RecordingDocumentationAnswerer(Answer::cited('25 m.', [new Citation('Notice inventée', 3)]));

        $answer = (new AskDocumentation(new StubPassageRetriever([new Passage('Notice PAC', 12, 'Liaison : 25 m.')]), $answerer))(Question::ask('Longueur ?'));

        $this->assertSame(Answer::NOT_FOUND, $answer->text);
    }

    public function testUnavailableModelIsPassedOn(): void
    {
        $this->expectException(AiUnavailable::class);

        (new AskDocumentation(new StubPassageRetriever([new Passage('N', 1, 'a')]), new RecordingDocumentationAnswerer(AiUnavailable::because('test'))))(Question::ask('question'));
    }
}
