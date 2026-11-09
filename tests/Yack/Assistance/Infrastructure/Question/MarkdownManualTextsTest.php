<?php

declare(strict_types=1);

namespace App\Tests\Yack\Assistance\Infrastructure\Question;

use App\Yack\Assistance\Domain\Manual;
use App\Yack\Assistance\Domain\ManualType;
use App\Yack\Assistance\Infrastructure\Library\JsonManualLibrary;
use App\Yack\Assistance\Infrastructure\Question\MarkdownManualTexts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Format des textes indexés, et les vrais textes du dossier data/yack/assistance/textes.
 *
 * @internal
 */
#[CoversClass(MarkdownManualTexts::class)]
final class MarkdownManualTextsTest extends TestCase
{
    public function testPagesSectionsParagraphsAndListItems(): void
    {
        $passages = MarkdownManualTexts::parse(<<<'MD'
            ---
            document: test
            title: Manuel de test
            langue: fr
            pages_indexees: 2, 5
            ---
            [page 2]
            ## Entretien
            Première ligne
            suite du paragraphe.

            - Élément un.
            - Élément deux.
            [page 5]
            Sans titre sur cette page.
            Model | Max. 20m | Max. 25m | Max. 10m
            MD, 'textes/test.md');

        $this->assertSame([
            ['page' => 2, 'section' => 'Entretien', 'text' => 'Première ligne suite du paragraphe.', 'layout' => 'texte'],
            ['page' => 2, 'section' => 'Entretien', 'text' => 'Élément un.', 'layout' => 'texte'],
            ['page' => 2, 'section' => 'Entretien', 'text' => 'Élément deux.', 'layout' => 'texte'],
            ['page' => 5, 'section' => null, 'text' => 'Sans titre sur cette page. Model | Max. 20m | Max. 25m | Max. 10m', 'layout' => 'tableau'],
        ], $passages);
    }

    public function testAPageWhereFiveHeadingsRepeatIsAGrid(): void
    {
        $grid = '';
        foreach (['Clignote-1 fois', 'Clignote-2 fois', 'Clignote-3 fois', 'Clignote-4 fois', 'Clignote-5 fois'] as $heading) {
            $grid .= "## {$heading}\nCellule.\n## {$heading}\nAutre cellule.\n";
        }

        $passages = MarkdownManualTexts::parse("[page 37]\n## Clignote\nTexte.\n## Clignote\nTexte.\n[page 38]\n{$grid}", 'textes/test.md');

        $this->assertSame(['texte'], array_values(array_unique(array_column(array_filter($passages, static fn (array $p): bool => 37 === $p['page']), 'layout'))));
        $this->assertSame(['grille'], array_values(array_unique(array_column(array_filter($passages, static fn (array $p): bool => 38 === $p['page']), 'layout'))));
    }

    public function testTextBeforeTheFirstPageIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('textes/test.md : texte avant le premier repère [page N].');

        MarkdownManualTexts::parse("---\ntitle: T\n---\nTexte", 'textes/test.md');
    }

    /** @return \Iterator<string, array{?string, list<int>}> */
    public static function pageLists(): \Iterator
    {
        yield 'liste et plages' => ['45, 82-83, 85-86, 96', [45, 82, 83, 85, 86, 96]];
        yield 'doublon' => ['4-6, 5', [4, 5, 6]];
        yield 'vide' => [null, []];
        yield 'illisible ignoré' => ['12, abc', [12]];
    }

    /** @param list<int> $expected */
    #[DataProvider('pageLists')]
    public function testIndexedPagesList(?string $indexedPages, array $expected): void
    {
        $this->assertSame($expected, MarkdownManualTexts::pages($indexedPages));
    }

    /** @return \Iterator<string, array{?string}> */
    public static function unreadable(): \Iterator
    {
        yield 'sans texte' => [null];
        yield 'hors du dossier des textes' => ['../manuels.json'];
        yield 'fichier absent' => ['textes/absent.md'];
    }

    #[DataProvider('unreadable')]
    public function testNoTextGivesNoPassage(?string $text): void
    {
        $manual = new Manual('test', 'Test', ManualType::Utilisation, ['fr'], 'https://manuels.example/t.pdf', true, ['SRK25ZS-W'], [], text: $text);

        $this->assertSame([], (new MarkdownManualTexts(self::directory()))->passages($manual));
    }

    public function testEveryRealDocumentWithTextGivesItsIndexedPages(): void
    {
        $texts = new MarkdownManualTexts(self::directory());
        $withText = array_values(array_filter((new JsonManualLibrary(self::directory() . '/manuels.json'))->documents(), static fn (Manual $manual): bool => $manual->hasText()));

        $this->assertTrue([] !== $withText, 'Aucun document avec texte dans la base.');
        foreach ($withText as $manual) {
            $passages = $texts->passages($manual);
            $pages = array_values(array_unique(array_column($passages, 'page')));
            $this->assertTrue([] !== $passages, $manual->id . ' : aucun passage.');
            $this->assertSame(MarkdownManualTexts::pages($manual->indexedPages), $pages, $manual->id);
        }
    }

    private static function directory(): string
    {
        return \dirname(__DIR__, 5) . '/data/yack/assistance';
    }
}
