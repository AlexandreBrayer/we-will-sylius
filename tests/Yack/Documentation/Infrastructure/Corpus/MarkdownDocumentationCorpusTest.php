<?php

declare(strict_types=1);

namespace App\Tests\Yack\Documentation\Infrastructure\Corpus;

use App\Yack\Documentation\Application\AskDocumentation;
use App\Yack\Documentation\Application\RetrievePassages;
use App\Yack\Documentation\Domain\Notice;
use App\Yack\Documentation\Domain\Passage;
use App\Yack\Documentation\Domain\Question;
use App\Yack\Documentation\Infrastructure\Assistant\FakeDocumentationAnswerer;
use App\Yack\Documentation\Infrastructure\Corpus\MarkdownDocumentationCorpus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * On the real FICTIF corpus of data/yack/demo/documentation.
 *
 * @internal
 */
#[CoversClass(MarkdownDocumentationCorpus::class)]
final class MarkdownDocumentationCorpusTest extends TestCase
{
    private MarkdownDocumentationCorpus $corpus;

    #[\Override]
    protected function setUp(): void
    {
        $this->corpus = new MarkdownDocumentationCorpus(\dirname(__DIR__, 5));
    }

    public function testTheDemoNoticesAreRead(): void
    {
        $notices = $this->corpus->notices();

        $this->assertSame([
            'Notice d\'installation Aquaflow 200 et 270 L (FICTIF)',
            'Notice d\'installation Evolys 8 kW (FICTIF)',
            'Notice d\'installation Kaze mural (FICTIF)',
        ], array_map(static fn (Notice $notice): string => $notice->title, $notices));
        $this->assertSame(['YK-PAE-M08'], $notices[1]->productCodes);
        $this->assertSame(['YK-PAA-K25', 'YK-PAA-K35', 'YK-PAA-K50'], $notices[2]->productCodes);
        $this->assertSame($notices, $this->corpus->notices());
    }

    public function testEveryNoticeIsMarkedFictifAndHasAnExample(): void
    {
        foreach ($this->corpus->notices() as $notice) {
            $this->assertStringContainsString('(FICTIF)', $notice->title);
            $this->assertTrue(null !== $notice->exampleQuestion && '' !== $notice->exampleQuestion);
            $this->assertTrue(\count($notice->passages) > 0);
            foreach ($notice->passages as $passage) {
                $this->assertSame($notice->productCodes, $passage->productCodes);
                $this->assertFalse(str_contains($passage->text, 'SPÉCIMEN'), 'avertissement indexé');
                $this->assertFalse(str_contains($passage->text, '**'));
            }
        }
    }

    public function testTheLinkLengthOfTheEvolys8IsOnPage12(): void
    {
        $answer = (new AskDocumentation(new RetrievePassages($this->corpus), new FakeDocumentationAnswerer()))(
            Question::ask('Quelle est la longueur de liaison de la PAC 8 kW ?', 'YK-PAE-M08'),
        );

        $this->assertStringContainsString('25 m', $answer->text);
        $this->assertSame([['source' => 'Notice d\'installation Evolys 8 kW (FICTIF)', 'page' => 12]], $answer->toArray()['sources']);
        $this->assertTrue($answer->simulated);
    }

    #[DataProvider('examples')]
    public function testTheExampleQuestionOfEachNoticeFindsAnAnswerInIt(int $index, string $code, int $page): void
    {
        $notice = $this->corpus->notices()[$index];
        $answer = (new AskDocumentation(new RetrievePassages($this->corpus), new FakeDocumentationAnswerer()))(
            Question::ask((string) $notice->exampleQuestion, $code),
        );

        $this->assertSame([['source' => $notice->title, 'page' => $page]], $answer->toArray()['sources']);
    }

    /** @return \Iterator<string, array{int, string, int}> */
    public static function examples(): \Iterator
    {
        yield 'Aquaflow' => [0, 'YK-ECS-T200', 16];
        yield 'Evolys' => [1, 'YK-PAE-M08', 12];
        yield 'Kaze' => [2, 'YK-PAA-K35', 8];
    }

    public function testOutOfCorpusQuestion(): void
    {
        $answer = (new AskDocumentation(new RetrievePassages($this->corpus), new FakeDocumentationAnswerer()))(Question::ask('Quelle est la capitale du Portugal ?'));

        $this->assertSame('Je ne trouve pas la réponse dans la documentation.', $answer->text);
        $this->assertSame([], $answer->sources);
    }

    public function testMissingDirectoryGivesNoNotice(): void
    {
        $this->assertSame([], (new MarkdownDocumentationCorpus(\dirname(__DIR__, 5), 'data/yack/demo/absent'))->notices());
    }

    public function testParseOfPagesSectionsListsAndWarnings(): void
    {
        $notice = MarkdownDocumentationCorpus::parse(<<<'MD'
            ---
            title: Notice test (FICTIF)
            product: Appareil test
            products: YK-A, YK-B
            ---
            [page 2]
            # Titre
            > Avertissement non indexé.
            ## Liaisons
            Première ligne
            **suite** du `paragraphe`.

            - Item un.
            - Item deux.
            [page 5]
            Dernier paragraphe.
            MD, 'test-FICTIF.md');

        $this->assertSame('Appareil test', $notice->productName);
        $this->assertNull($notice->exampleQuestion);
        $this->assertSame([
            [2, 'Liaisons', 'Première ligne suite du paragraphe.'],
            [2, 'Liaisons', 'Item un.'],
            [2, 'Liaisons', 'Item deux.'],
            [5, 'Liaisons', 'Dernier paragraphe.'],
        ], array_map(static fn (Passage $p): array => [$p->page, $p->section, $p->text], $notice->passages));
        $this->assertSame(['YK-A', 'YK-B'], $notice->passages[0]->productCodes);
    }

    #[DataProvider('invalidNotices')]
    public function testInvalidNoticeIsRefused(string $markdown, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        MarkdownDocumentationCorpus::parse($markdown, 'x-FICTIF.md');
    }

    /** @return \Iterator<string, array{string, string}> */
    public static function invalidNotices(): \Iterator
    {
        yield 'sans en-tête' => ["[page 1]\nTexte", 'en-tête « --- » manquant'];
        yield 'en-tête non fermé' => ["---\ntitle: T\n[page 1]", 'en-tête non fermé'];
        yield 'sans titre' => ["---\nproducts: YK-A\n---\n[page 1]\nTexte", '« title » manquant'];
        yield 'texte sans page' => ["---\ntitle: T\n---\nTexte", 'avant le premier repère'];
    }
}
