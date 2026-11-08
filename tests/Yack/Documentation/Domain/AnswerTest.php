<?php

declare(strict_types=1);

namespace App\Tests\Yack\Documentation\Domain;

use App\Yack\Documentation\Domain\Answer;
use App\Yack\Documentation\Domain\AnswerWithoutSource;
use App\Yack\Documentation\Domain\Citation;
use App\Yack\Documentation\Domain\Passage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Answer::class)]
#[CoversClass(AnswerWithoutSource::class)]
final class AnswerTest extends TestCase
{
    public function testCitedAnswerKeepsItsSourcesOnce(): void
    {
        $answer = Answer::cited(' 25 m en aller simple. ', [new Citation('Notice Evolys', 12), new Citation('Notice Evolys', 12), new Citation('Notice Evolys', 18)], simulated: true);

        $this->assertTrue($answer->isFound());
        $this->assertSame([
            'answer' => '25 m en aller simple.',
            'sources' => [['source' => 'Notice Evolys', 'page' => 12], ['source' => 'Notice Evolys', 'page' => 18]],
            'simulated' => true,
        ], $answer->toArray());
    }

    public function testAnswerWithoutSourceIsRefused(): void
    {
        $this->expectException(AnswerWithoutSource::class);
        $this->expectExceptionMessage('Réponse sans source refusée');

        Answer::cited('La liaison fait 25 m.', []);
    }

    public function testEmptyAnswerIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Answer::cited('  ', [new Citation('Notice Evolys', 12)]);
    }

    public function testNotFoundIsTheOnlyAnswerWithoutSource(): void
    {
        $answer = Answer::notFound();

        $this->assertFalse($answer->isFound());
        $this->assertSame(['answer' => 'Je ne trouve pas la réponse dans la documentation.', 'sources' => [], 'simulated' => false], $answer->toArray());
        $this->assertTrue(Answer::notFound(true)->simulated);
    }

    public function testLongAnswerIsCut(): void
    {
        $answer = Answer::cited(str_repeat('é', Answer::MAX_LENGTH + 10), [new Citation('Notice', 1)]);

        $this->assertSame(Answer::MAX_LENGTH, mb_strlen($answer->text));
    }

    public function testRestrictedToKeepsOnlyTheSourcesOfThePassages(): void
    {
        $passages = [new Passage('Notice Evolys', 12, 'Liaison : 25 m.')];
        $answer = Answer::cited('25 m.', [new Citation('Notice Evolys', 12), new Citation('Notice inventée', 3)], simulated: true);

        $restricted = $answer->restrictedTo($passages);

        $this->assertSame([['source' => 'Notice Evolys', 'page' => 12]], $restricted->toArray()['sources']);
        $this->assertSame('25 m.', $restricted->text);
        $this->assertTrue($restricted->simulated);
    }

    public function testRestrictedToWithoutAnyValidSourceBecomesNotFound(): void
    {
        $answer = Answer::cited('25 m.', [new Citation('Notice Evolys', 99)]);

        $restricted = $answer->restrictedTo([new Passage('Notice Evolys', 12, 'Liaison : 25 m.')]);

        $this->assertFalse($restricted->isFound());
        $this->assertSame(Answer::NOT_FOUND, $restricted->text);
    }

    public function testASourceWrittenOtherwiseIsKeptAsThePassageWritesIt(): void
    {
        $passages = [new Passage('mhi-srk-zs-w-utilisation-rlf012a109j-fr', 34, 'Nettoyez le filtre.'), new Passage('Notice Évolys 8 kW', 12, 'Liaison : 25 m.')];
        $answer = Answer::cited('Filtre et liaison.', [new Citation('MHI SRK ZS W utilisation RLF012A109J FR', 34), new Citation('notice evolys 8 kw.', 12), new Citation('notice evolys 8 kw', 13)]);

        $this->assertSame(
            [['source' => 'mhi-srk-zs-w-utilisation-rlf012a109j-fr', 'page' => 34], ['source' => 'Notice Évolys 8 kW', 'page' => 12]],
            $answer->restrictedTo($passages)->toArray()['sources'],
        );
    }

    public function testASourceWrittenOtherwiseThatFitsTwoPassageSourcesIsDropped(): void
    {
        $passages = [new Passage('Notice Evolys', 12, 'Liaison : 25 m.'), new Passage('Notice : Évolys', 12, 'Liaison : 30 m.')];

        $restricted = Answer::cited('25 m.', [new Citation('notice evolys', 12)])->restrictedTo($passages);

        $this->assertFalse($restricted->isFound());
    }

    public function testNotFoundStaysNotFound(): void
    {
        $answer = Answer::notFound();

        $this->assertSame($answer, $answer->restrictedTo([new Passage('Notice', 1, 'Texte')]));
    }
}
