<?php

declare(strict_types=1);

namespace App\Tests\Yack\Documentation\Application;

use App\Yack\Common\Domain\AiUnavailable;
use App\Yack\Documentation\Application\AnswerFromNotices;
use App\Yack\Documentation\Domain\Answer;
use App\Yack\Documentation\Domain\Citation;
use App\Yack\Documentation\Domain\DocumentationAnswerer;
use App\Yack\Documentation\Domain\Notice;
use App\Yack\Documentation\Domain\Passage;
use App\Yack\Documentation\Domain\Question;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Notices written by hand, answerer that records what it receives.
 *
 * @internal
 */
#[CoversClass(AnswerFromNotices::class)]
final class AnswerFromNoticesTest extends TestCase
{
    private const MANUAL = 'Manuel de test';
    private const TECHNICAL = 'Technical manual (test)';

    public function testTheAnswerIsWrittenFromThePassagesOfTheseNoticesOnly(): void
    {
        $answerer = new RecordingDocumentationAnswerer(Answer::cited('Nettoyez le filtre à l\'eau.', [new Citation(self::MANUAL, 33)], simulated: true));

        $answer = (new AnswerFromNotices($answerer))->answer(Question::ask('Comment nettoyer le filtre ?'), self::notices());

        $this->assertSame('Nettoyez le filtre à l\'eau.', $answer->text);
        $this->assertSame([['source' => self::MANUAL, 'page' => 33]], $answer->toArray()['sources']);
        $this->assertTrue($answer->simulated);
        $this->assertCount(1, $answerer->received);
        $this->assertSame([[self::MANUAL, 33], [self::MANUAL, 34]], self::pages($answerer->received[0]));
    }

    public function testNothingRelevantIsNotFoundWithoutCallingTheAnswerer(): void
    {
        $answerer = new RecordingDocumentationAnswerer(new \LogicException('aucun appel attendu'));

        $answer = (new AnswerFromNotices($answerer))->answer(Question::ask('Quelle est la capitale du Portugal ?'), self::notices());

        $this->assertSame(Answer::NOT_FOUND, $answer->text);
        $this->assertSame([], $answer->sources);
        $this->assertSame([], $answerer->received);
    }

    public function testNoNoticeIsNotFoundWithoutCallingTheAnswerer(): void
    {
        $answerer = new RecordingDocumentationAnswerer(new \LogicException('aucun appel attendu'));

        $answer = (new AnswerFromNotices($answerer))->answer(Question::ask('Comment nettoyer le filtre ?'), []);

        $this->assertSame(Answer::NOT_FOUND, $answer->text);
        $this->assertSame([], $answerer->received);
    }

    public function testASourceOutsideThePassagesReadIsRemoved(): void
    {
        $answerer = new RecordingDocumentationAnswerer(Answer::cited('Filtre.', [new Citation(self::MANUAL, 33), new Citation(self::MANUAL, 99), new Citation('Autre manuel', 33)]));

        $answer = (new AnswerFromNotices($answerer))->answer(Question::ask('Comment nettoyer le filtre ?'), self::notices());

        $this->assertSame([['source' => self::MANUAL, 'page' => 33]], $answer->toArray()['sources']);
    }

    public function testOnlyInventedSourcesGiveNotFound(): void
    {
        $answerer = new RecordingDocumentationAnswerer(Answer::cited('Filtre.', [new Citation(self::MANUAL, 99)]));

        $answer = (new AnswerFromNotices($answerer))->answer(Question::ask('Comment nettoyer le filtre ?'), self::notices());

        $this->assertSame(Answer::NOT_FOUND, $answer->text);
    }

    public function testARewordingFindsWhatTheQuestionCannotAndTheAnswererGetsTheQuestion(): void
    {
        $answerer = new class implements DocumentationAnswerer {
            /** @var list<string> */
            public array $questions = [];

            /** @var list<list<Passage>> */
            public array $received = [];

            public function answer(Question $question, array $passages): Answer
            {
                $this->questions[] = $question->text;
                $this->received[] = $passages;

                return Answer::cited($passages[0]->text, [$passages[0]->citation()]);
            }
        };
        $question = Question::ask('Quelle longueur de liaison maximale ?');

        $without = (new AnswerFromNotices($answerer))->answer($question, self::notices());
        $with = (new AnswerFromNotices($answerer))->answer($question, self::notices(), ['line length max']);

        $this->assertSame(Answer::NOT_FOUND, $without->text);
        $this->assertSame([['source' => self::TECHNICAL, 'page' => 45]], $with->toArray()['sources']);
        $this->assertSame(['Quelle longueur de liaison maximale ?'], $answerer->questions);
    }

    public function testWordingsAreMergedInTurnWithoutDuplicateUpToTheLimit(): void
    {
        $answerer = new RecordingDocumentationAnswerer(Answer::cited('Filtre.', [new Citation(self::MANUAL, 33)]));

        (new AnswerFromNotices($answerer, 3))->answer(
            Question::ask('Nettoyage du filtre ?'),
            self::notices(),
            ['active filter error', 'filtre nettoyage', 'x', str_repeat('mot ', 100)],
        );

        $this->assertSame([[self::MANUAL, 33], [self::TECHNICAL, 82], [self::MANUAL, 34]], self::pages($answerer->received[0]));
    }

    public function testAnUnavailableModelIsRaised(): void
    {
        $this->expectException(AiUnavailable::class);

        (new AnswerFromNotices(new RecordingDocumentationAnswerer(AiUnavailable::because('plafond quotidien atteint'))))
            ->answer(Question::ask('Comment nettoyer le filtre ?'), self::notices());
    }

    /** @return list<Notice> */
    private static function notices(): array
    {
        return [
            new Notice(self::MANUAL, 'SRK25ZS-W', ['SRK25ZS-W'], null, [
                new Passage(self::MANUAL, 33, 'Nettoyage du filtre à air : lavez-le à l\'eau tiède.', ['SRK25ZS-W'], 'Entretien'),
                new Passage(self::MANUAL, 34, 'Remplacez le filtre hypoallergénique chaque année ; nettoyage à l\'aspirateur.', ['SRK25ZS-W'], 'Entretien'),
            ]),
            new Notice(self::TECHNICAL, 'SRK25ZS-WF', ['SRK25ZS-WF'], null, [
                new Passage(self::TECHNICAL, 45, 'Refrigerant line (one way) length | Max. 20m | Max. 25m', ['SRK25ZS-WF']),
                new Passage(self::TECHNICAL, 82, 'Active filter voltage error | Defective active filter', ['SRK25ZS-WF']),
            ]),
        ];
    }

    /**
     * @param list<Passage> $passages
     *
     * @return list<array{string, int}>
     */
    private static function pages(array $passages): array
    {
        return array_map(static fn (Passage $passage): array => [$passage->source, $passage->page], $passages);
    }
}
