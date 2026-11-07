<?php

declare(strict_types=1);

namespace App\Tests\Yack\Documentation\Infrastructure\Assistant;

use App\Yack\Common\Domain\AiInput;
use App\Yack\Common\Domain\AiUnavailable;
use App\Yack\Documentation\Domain\Answer;
use App\Yack\Documentation\Domain\Passage;
use App\Yack\Documentation\Domain\Question;
use App\Yack\Documentation\Infrastructure\Assistant\AnthropicDocumentationAnswerer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AnthropicDocumentationAnswerer::class)]
final class AnthropicDocumentationAnswererTest extends TestCase
{
    /** @var list<Passage> */
    private array $passages;

    #[\Override]
    protected function setUp(): void
    {
        $this->passages = [
            new Passage('Notice Evolys 8 kW (FICTIF)', 12, 'La longueur maximale de la liaison hydraulique est de 25 m.', ['YK-PAE-M08'], 'Liaisons'),
            new Passage('Notice Evolys 8 kW (FICTIF)', 18, 'E5 : défaut de communication.', ['YK-PAE-M08']),
        ];
    }

    public function testOnlyTheQuestionAndThePassagesAreSentWithStrictInstructions(): void
    {
        $model = new FakeLanguageModel(['answer' => '25 m.', 'sources' => [['source' => 'Notice Evolys 8 kW (FICTIF)', 'page' => 12]]]);

        (new AnthropicDocumentationAnswerer($model))->answer(Question::ask('Longueur de liaison ?', 'YK-PAE-M08'), $this->passages);

        $call = $model->calls[0];
        $this->assertSame('documentation.answer', $call['purpose']);
        $this->assertStringContainsString('uniquement à partir des passages', $call['instructions']);
        $this->assertStringContainsString('Je ne trouve pas la réponse dans la documentation.', $call['instructions']);
        $this->assertCount(1, $call['inputs']);
        $this->assertSame(AiInput::TEXT, $call['inputs'][0]->kind);
        $this->assertSame(
            "Question : Longueur de liaison ?\nProduit concerné : YK-PAE-M08\n\nPassages de la documentation (seule source autorisée) :\n\n"
            ."[1] Source : Notice Evolys 8 kW (FICTIF) | Page : 12 | Section : Liaisons\nLa longueur maximale de la liaison hydraulique est de 25 m.\n\n"
            ."[2] Source : Notice Evolys 8 kW (FICTIF) | Page : 18\nE5 : défaut de communication.",
            $call['inputs'][0]->content,
        );
        $this->assertSame(['answer', 'sources'], $call['schema']['required']);
        $this->assertSame(['source', 'page'], $call['schema']['properties']['sources']['items']['required']);
    }

    public function testAnswerCitingThePassagesIsKept(): void
    {
        $model = new FakeLanguageModel(['answer' => ' 25 m en aller simple. ', 'sources' => [['source' => 'Notice Evolys 8 kW (FICTIF)', 'page' => '12']]]);

        $answer = (new AnthropicDocumentationAnswerer($model))->answer(Question::ask('Longueur ?'), $this->passages);

        $this->assertSame(['answer' => '25 m en aller simple.', 'sources' => [['source' => 'Notice Evolys 8 kW (FICTIF)', 'page' => 12]], 'simulated' => false], $answer->toArray());
    }

    public function testASourceThatWasNotSentIsRemoved(): void
    {
        $model = new FakeLanguageModel(['answer' => '25 m.', 'sources' => [
            ['source' => 'Notice Evolys 8 kW (FICTIF)', 'page' => 12],
            ['source' => 'Notice Evolys 8 kW (FICTIF)', 'page' => 40],
            ['source' => 'Une notice inventée', 'page' => 12],
        ]]);

        $answer = (new AnthropicDocumentationAnswerer($model))->answer(Question::ask('Longueur ?'), $this->passages);

        $this->assertSame([['source' => 'Notice Evolys 8 kW (FICTIF)', 'page' => 12]], $answer->toArray()['sources']);
    }

    /** @param array<string, mixed> $result */
    #[DataProvider('notFoundResults')]
    public function testUnsupportedAnswerBecomesNotFound(array $result): void
    {
        $answer = (new AnthropicDocumentationAnswerer(new FakeLanguageModel($result)))->answer(Question::ask('Longueur ?'), $this->passages);

        $this->assertSame(Answer::NOT_FOUND, $answer->text);
        $this->assertSame([], $answer->sources);
        $this->assertFalse($answer->simulated);
    }

    /** @return \Iterator<string, array{array<string, mixed>}> */
    public static function notFoundResults(): \Iterator
    {
        yield 'aucune source' => [['answer' => '25 m.', 'sources' => []]];
        yield 'sources absentes' => [['answer' => '25 m.']];
        yield 'seulement des sources inventées' => [['answer' => '25 m.', 'sources' => [['source' => 'Autre notice', 'page' => 3]]]];
        yield 'sources mal formées' => [['answer' => '25 m.', 'sources' => ['page 12', ['source' => '', 'page' => 12], ['source' => 'Notice Evolys 8 kW (FICTIF)', 'page' => 0], ['source' => 'Notice Evolys 8 kW (FICTIF)', 'page' => 'douze']]]];
        yield 'réponse vide' => [['answer' => '  ', 'sources' => [['source' => 'Notice Evolys 8 kW (FICTIF)', 'page' => 12]]]];
        yield 'réponse non textuelle' => [['answer' => 25, 'sources' => [['source' => 'Notice Evolys 8 kW (FICTIF)', 'page' => 12]]]];
        yield 'le modèle dit ne pas savoir' => [['answer' => 'Je ne trouve pas la réponse dans la documentation.', 'sources' => [['source' => 'Notice Evolys 8 kW (FICTIF)', 'page' => 12]]]];
    }

    public function testNoPassageNoCall(): void
    {
        $model = new FakeLanguageModel(['answer' => 'x', 'sources' => []]);

        $answer = (new AnthropicDocumentationAnswerer($model))->answer(Question::ask('Longueur ?'), []);

        $this->assertSame(Answer::NOT_FOUND, $answer->text);
        $this->assertSame([], $model->calls);
    }

    public function testUnavailableModelIsPassedOn(): void
    {
        $this->expectException(AiUnavailable::class);

        (new AnthropicDocumentationAnswerer(new FakeLanguageModel(AiUnavailable::because('trop de demandes'))))->answer(Question::ask('Longueur ?'), $this->passages);
    }
}
