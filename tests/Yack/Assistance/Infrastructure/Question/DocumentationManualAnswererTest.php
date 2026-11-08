<?php

declare(strict_types=1);

namespace App\Tests\Yack\Assistance\Infrastructure\Question;

use App\Yack\Assistance\Application\AskManuals;
use App\Yack\Assistance\Domain\Manual;
use App\Yack\Assistance\Domain\ManualAnswer;
use App\Yack\Assistance\Domain\ManualType;
use App\Yack\Assistance\Infrastructure\Library\JsonManualLibrary;
use App\Yack\Assistance\Infrastructure\Question\DocumentationManualAnswerer;
use App\Yack\Assistance\Infrastructure\Question\MarkdownManualTexts;
use App\Yack\Documentation\Application\AnswerFromNotices;
use App\Yack\Documentation\Application\AnswerFromNoticesInterface;
use App\Yack\Documentation\Domain\Answer;
use App\Yack\Documentation\Domain\Citation;
use App\Yack\Documentation\Domain\Notice;
use App\Yack\Documentation\Domain\Passage;
use App\Yack\Documentation\Domain\Question;
use App\Yack\Documentation\Infrastructure\Assistant\FakeDocumentationAnswerer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Textes écrits à la main (dossier manuels-test, à côté de ce test ; page 38 : une grille éclatée) et moteur de Documentation remplacé par un faux qui enregistre ;
 * puis les questions de recette sur les vrais textes, avec le vrai moteur et l'answerer de démonstration.
 *
 * @internal
 */
#[CoversClass(DocumentationManualAnswerer::class)]
final class DocumentationManualAnswererTest extends TestCase
{
    private const FRENCH = 'Manuel de l\'utilisateur SRK ZS-W (test)';
    private const ENGLISH = 'Technical manual SRK ZS-WF (test)';

    /** @var list<array{question: string, notices: list<Notice>, rewordings: list<string>}> */
    private array $calls = [];

    #[\Override]
    protected function setUp(): void
    {
        $this->calls = [];
    }

    public function testEachManualBecomesANoticeOfItsPassages(): void
    {
        $this->answerer(Answer::notFound())->answer('Que signifie le code erreur E 16 ?', [self::french(), self::english()]);

        [$french, $english] = $this->calls[0]['notices'];
        $this->assertSame([self::FRENCH, 'SRK20ZS-W SRK25ZS-W', ['SRK25ZS-W']], [$french->title, $french->productName, $french->productCodes]);
        $this->assertSame([[33, 'Entretien', 'Nettoyez le filtre à l\'eau.']], self::passages($french));
        $this->assertSame([[82, null, '(5) Self-diagnosis table'], [82, null, '6-time | Indoor fan | ON | E16 | motor error']], self::passages($english));
        $this->assertSame('Que signifie le code erreur E16 ?', $this->calls[0]['question']);
        $this->assertSame(['E16 ?', 'error E16'], $this->calls[0]['rewordings']);
    }

    public function testNoEnglishFormWhenEveryManualIsInFrench(): void
    {
        $this->answerer(Answer::notFound())->answer('Que signifie le code erreur E 16 ?', [self::french()]);

        $this->assertSame(['E16 ?'], $this->calls[0]['rewordings']);
    }

    public function testASourceBecomesTheManualAndItsPageAndATableCarriesTheWarning(): void
    {
        $answer = $this->answerer(Answer::cited('E16 : moteur du ventilateur.', [new Citation(self::ENGLISH, 82)], simulated: true))
            ->answer('Code E16 ?', [self::french(), self::english()]);

        $this->assertSame([['english', 82]], self::citations($answer));
        $this->assertSame('E16 : moteur du ventilateur. ' . ManualAnswer::TABLE_WARNING, $answer->text);
        $this->assertTrue($answer->simulated);
        $this->assertTrue($answer->fromTable);
    }

    public function testATextSourceCarriesNoWarning(): void
    {
        $answer = $this->answerer(Answer::cited('Nettoyez le filtre à l\'eau.', [new Citation(self::FRENCH, 33)]))
            ->answer('Comment nettoyer le filtre ?', [self::french(), self::english()]);

        $this->assertSame([['french', 33]], self::citations($answer));
        $this->assertSame('Nettoyez le filtre à l\'eau.', $answer->text);
        $this->assertFalse($answer->fromTable);
    }

    public function testNotFoundAndUnknownSourcesGiveNotFound(): void
    {
        $this->assertSame(ManualAnswer::NOT_FOUND, $this->answerer(Answer::notFound())->answer('Capitale du Portugal ?', [self::french()])->text);
        $this->assertSame(ManualAnswer::NOT_FOUND, $this->answerer(Answer::cited('Lisbonne.', [new Citation('Autre', 1)]))->answer('Capitale du Portugal ?', [self::french()])->text);
    }

    public function testTwoManualsWithTheSameTitleAreToldApart(): void
    {
        $twin = new Manual('twin', self::FRENCH, ManualType::Utilisation, ['fr'], 'https://manuels.example/twin.pdf', true, ['SRK25ZS-W'], [], text: 'textes/fr.md', indexedPages: '33, 38');

        $answer = $this->answerer(Answer::cited('Filtre.', [new Citation(self::FRENCH . ' (twin)', 33)]))->answer('Comment nettoyer le filtre ?', [self::french(), $twin]);

        $this->assertSame([self::FRENCH, self::FRENCH . ' (twin)'], array_map(static fn (Notice $notice): string => $notice->title, $this->calls[0]['notices']));
        $this->assertSame([['twin', 33]], self::citations($answer));
    }

    public function testManualsWithoutReadableTextAreNotIndexedAndNothingIsAsked(): void
    {
        $missing = new Manual('missing', 'Absent', ManualType::Utilisation, ['fr'], 'https://manuels.example/a.pdf', true, ['SRK25ZS-W'], [], text: 'textes/absent.md');

        $answer = $this->answerer(Answer::notFound())->answer('Comment nettoyer le filtre ?', [$missing]);

        $this->assertSame(ManualAnswer::NOT_INDEXED, $answer->text);
        $this->assertSame([], $this->calls);
    }

    public function testACodeQuestionTooShortOnceGluedStaysAsTyped(): void
    {
        $this->answerer(Answer::notFound())->answer('E 5', [self::english()]);

        $this->assertSame('E 5', $this->calls[0]['question']);
        $this->assertSame(['E5 ?'], $this->calls[0]['rewordings']);
    }

    /** @return \Iterator<string, array{string, string, ?string, ?int, bool}> */
    public static function acceptance(): \Iterator
    {
        yield 'filtre' => ['Comment nettoyer le filtre ?', 'SRK25ZS-W', 'mhi-srk-zs-w-utilisation-rlf012a109j-fr', 34, false];
        yield 'voyant RUN, la grille de la page 38 écartée' => ['Le voyant RUN clignote, que faire ?', 'SRK25ZS-W', 'mhi-srk-zs-w-utilisation-rlf012a109j-fr', 10, false];
        yield 'code E 16' => ['Que signifie le code erreur E 16 ?', 'SRK25ZS-W', 'mhi-src-srk-zs-w-technique-22-srk-t-359', 82, true];
        yield 'code E5' => ['Que signifie le code E5 ?', 'SRK25ZS-W', 'mhi-src-srk-zs-w-technique-22-srk-t-359', 82, true];
        yield 'longueur de liaison' => ['Quelle longueur de liaison maximale ?', 'SRK25ZS-W', 'mhi-src-srk-zs-w-technique-22-srk-t-359', 45, true];
        yield 'filtre, gainable FDUT' => ['Comment nettoyer le filtre ?', 'FDUT15KXE6F-W', 'mhi-fde-multi-utilisation-psb012a024d-fr', 15, false];
        yield 'hors des manuels' => ['Quelle est la capitale du Portugal ?', 'SRK25ZS-W', null, null, false];
    }

    #[DataProvider('acceptance')]
    public function testAcceptanceQuestionsOnTheRealTexts(string $question, string $reference, ?string $document, ?int $page, bool $fromTable): void
    {
        $root = \dirname(__DIR__, 5) . '/data/yack/assistance';
        $ask = new AskManuals(
            new JsonManualLibrary($root . '/manuels.json'),
            new DocumentationManualAnswerer(new MarkdownManualTexts($root), new AnswerFromNotices(new FakeDocumentationAnswerer())),
        );

        $answer = $ask($question, $reference);

        $this->assertSame(null === $document ? [] : [[$document, $page]], self::citations($answer));
        $this->assertSame($fromTable, $answer->fromTable);
        if (null === $document) {
            $this->assertSame(ManualAnswer::NOT_FOUND, $answer->text);
        }
        $this->assertSame(null !== $document, $answer->simulated);
    }

    public function testAModelWithoutIndexedTextGetsAClearAnswerOnTheRealBase(): void
    {
        $root = \dirname(__DIR__, 5) . '/data/yack/assistance';
        $ask = new AskManuals(
            new JsonManualLibrary($root . '/manuels.json'),
            new DocumentationManualAnswerer(new MarkdownManualTexts($root), new AnswerFromNotices(new FakeDocumentationAnswerer())),
        );

        $this->assertSame(ManualAnswer::NOT_INDEXED, $ask('Comment nettoyer le filtre ?', 'FDC224KXZME1')->text);
    }

    private function answerer(Answer $result): DocumentationManualAnswerer
    {
        $calls = &$this->calls;

        return new DocumentationManualAnswerer(new MarkdownManualTexts(__DIR__ . '/manuels-test'), new class($calls, $result) implements AnswerFromNoticesInterface {
            /** @param list<array{question: string, notices: list<Notice>, rewordings: list<string>}> $calls */
            public function __construct(private array &$calls, private readonly Answer $result)
            {
            }

            public function answer(Question $question, array $notices, array $rewordings = []): Answer
            {
                $this->calls[] = ['question' => $question->text, 'notices' => $notices, 'rewordings' => $rewordings];

                return $this->result;
            }
        });
    }

    private static function french(): Manual
    {
        return new Manual('french', self::FRENCH, ManualType::Utilisation, ['fr'], 'https://manuels.example/fr.pdf', true,
            ['SRK20ZS-W', 'SRK25ZS-W'], ['SRK25ZS-W'], 'MHI', text: 'textes/fr.md', indexedPages: '33, 38');
    }

    private static function english(): Manual
    {
        return new Manual('english', self::ENGLISH, ManualType::Technique, ['en'], 'https://manuels.example/en.pdf', true,
            ['SRK25ZS-WF'], [], 'MHI', text: 'textes/en.md', indexedPages: '82');
    }

    /** @return list<array{int, ?string, string}> */
    private static function passages(Notice $notice): array
    {
        return array_map(static fn (Passage $passage): array => [$passage->page, $passage->section, $passage->text], $notice->passages);
    }

    /** @return list<array{string, int}> */
    private static function citations(ManualAnswer $answer): array
    {
        return array_map(static fn (array $citation): array => [$citation['manual']->id, $citation['page']], $answer->citations);
    }
}
