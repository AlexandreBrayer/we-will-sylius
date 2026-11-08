<?php

declare(strict_types=1);

namespace App\Tests\Yack\Assistance\Infrastructure\Question;

use App\Yack\Assistance\Infrastructure\Question\ManualVocabulary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ManualVocabulary::class)]
final class ManualVocabularyTest extends TestCase
{
    /** @return \Iterator<string, array{string, string}> */
    public static function glued(): \Iterator
    {
        yield 'code du manuel' => ['ON | E 16 | -conditioner', 'ON | E16 | -conditioner'];
        yield 'code d\'un chiffre' => ['Error of signal ON | E 5 | Broken', 'Error of signal ON | E5 | Broken'];
        yield 'question en minuscules' => ['code erreur e 16 ?', 'code erreur e16 ?'];
        yield 'déjà accolé' => ['Que signifie E16 ?', 'Que signifie E16 ?'];
        yield '« a » reste un mot' => ['il y a 5 minutes', 'il y a 5 minutes'];
        yield '« à » reste un mot' => ['de 3 à 5 m', 'de 3 à 5 m'];
        yield 'fin de mot non accolée' => ['RUN 2 fois', 'RUN 2 fois'];
        yield 'nombre de plus de 3 chiffres' => ['E 1234', 'E 1234'];
    }

    #[DataProvider('glued')]
    public function testALetterAloneIsGluedToItsNumber(string $text, string $expected): void
    {
        $this->assertSame($expected, ManualVocabulary::glueCodes($text));
    }

    public function testCodesOfTheQuestion(): void
    {
        $this->assertSame('E16 ?', ManualVocabulary::codes('Que signifie le code erreur E 16 ?'));
        $this->assertSame('E5 E16 ?', ManualVocabulary::codes('E5 puis E16, puis E5'));
        $this->assertNull(ManualVocabulary::codes('Comment nettoyer le filtre ?'));
    }

    /** @return \Iterator<string, array{string, ?string}> */
    public static function english(): \Iterator
    {
        yield 'filtre' => ['Comment nettoyer le filtre ?', 'clean cleaning filter'];
        yield 'voyant, mots en capitales gardés' => ['Le voyant RUN clignote, que faire ?', 'lamp light RUN flash flashing blink'];
        yield 'code accolé gardé' => ['Que signifie le code erreur E 16 ?', 'error E16'];
        yield 'pluriel et accents' => ['Quelle longueur des liaisons maximales ?', 'length line piping max maximum'];
        yield 'rien à traduire' => ['Quelle est la capitale du Portugal ?', null];
    }

    #[DataProvider('english')]
    public function testEnglishFormOfTheQuestion(string $question, ?string $expected): void
    {
        $this->assertSame($expected, ManualVocabulary::english($question));
    }

    public function testRewordings(): void
    {
        $this->assertSame(['E16 ?', 'error E16'], ManualVocabulary::rewordings('Que signifie le code erreur E 16 ?', true));
        $this->assertSame(['E16 ?'], ManualVocabulary::rewordings('Que signifie le code erreur E 16 ?', false));
        $this->assertSame([], ManualVocabulary::rewordings('Quelle est la capitale du Portugal ?', true));
    }
}
