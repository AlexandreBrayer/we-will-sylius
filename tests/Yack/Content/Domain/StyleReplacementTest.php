<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Yack\Content\Domain\InvalidStyleReplacement;
use App\Yack\Content\Domain\StyleReplacement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(StyleReplacement::class)]
final class StyleReplacementTest extends TestCase
{
    public function testOnlyThePropertiesThatAcceptAFreeValueCanBeReplaced(): void
    {
        $this->assertSame(['fond', 'texte', 'accent', 'titre', 'hautEspace', 'basEspace', 'gaucheEspace', 'droiteEspace'], StyleReplacement::properties());
    }

    public function testTheNewValueIsAKeyOfTheCharteOfTheSameKind(): void
    {
        $this->assertSame(['magenta', 'magenta-dark', 'plum', 'teal', 'teal-dark', 'teal-deep', 'yellow', 'ochre', 'dark', 'gray', 'light', 'white', 'black'], array_column(StyleReplacement::charteValues('fond'), 'key'));
        $this->assertSame(['s', 'm', 'l', 'xl'], array_column(StyleReplacement::charteValues('titre'), 'key'));
        $this->assertSame(['0', 's', 'm', 'l', 'xl'], array_column(StyleReplacement::charteValues('basEspace'), 'key'));
        $this->assertSame(['key' => 'magenta', 'label' => 'Magenta', 'value' => '#a33c87'], StyleReplacement::charteValues('accent')[0]);
        $this->assertSame([], StyleReplacement::charteValues('alignement'));
        $this->assertSame([], StyleReplacement::charteValues('inconnu'));
    }

    public function testAReplacementKeepsTheFreeValueAsBlocksStoreIt(): void
    {
        $replacement = StyleReplacement::of('fond', '#FF7700', 'magenta');

        $this->assertSame('fond', $replacement->property);
        $this->assertSame('#ff7700', $replacement->from);
        $this->assertSame('magenta', $replacement->to);
        $this->assertSame('Magenta (#a33c87)', $replacement->toLabel());
        $this->assertSame('#a33c87', $replacement->toValue());
    }

    public function testSizesAndSpacingsAreReplacedByTheirCharteKey(): void
    {
        $this->assertSame('Grand (40px)', StyleReplacement::of('titre', '44px', 'l')->toLabel());
        $this->assertSame('Aucun (0px)', StyleReplacement::of('hautEspace', '120px', '0')->toLabel());
        $this->assertSame('0px', StyleReplacement::of('hautEspace', '120px', '0')->toValue());
        $this->assertSame('Grand (48px)', StyleReplacement::of('gaucheEspace', '120px', 'l')->toLabel());
        $this->assertSame('16px', StyleReplacement::of('droiteEspace', '8px', 's')->toValue());
    }

    #[DataProvider('provideRefusedReplacements')]
    public function testARefusedReplacementSaysWhy(string $property, string $from, string $to, string $message): void
    {
        $this->expectException(InvalidStyleReplacement::class);
        $this->expectExceptionMessage($message);

        StyleReplacement::of($property, $from, $to);
    }

    /**
     * @return \Iterator<string, array{string, string, string, string}>
     */
    public static function provideRefusedReplacements(): \Iterator
    {
        yield 'property without free value' => ['alignement', 'centre', 'gauche', 'Le réglage « Alignement » n\'a pas de valeur hors charte.'];
        yield 'title tag' => ['balise', 'h1', 'h2', 'Le réglage « Balise » n\'a pas de valeur hors charte.'];
        yield 'vertical alignment' => ['alignementVertical', 'bas', 'haut', 'Le réglage « Alignement vertical » n\'a pas de valeur hors charte.'];
        yield 'unknown property' => ['police', '#ff7700', 'magenta', 'Le réglage « police » n\'a pas de valeur hors charte.'];
        yield 'empty free value' => ['fond', '', 'magenta', 'Choisissez la valeur hors charte à remplacer pour « Fond ».'];
        yield 'malformed colour' => ['fond', 'red', 'magenta', '« red » n\'est pas une valeur valide pour « Fond ».'];
        yield 'css injection' => ['fond', '#ff7700;background:url(x)', 'magenta', 'n\'est pas une valeur valide pour « Fond ».'];
        yield 'size out of bounds' => ['titre', '200px', 'l', '« 200px » n\'est pas une valeur valide pour « Taille du titre ».'];
        yield 'charte key as free value' => ['fond', 'teal', 'magenta', '« teal » est déjà une valeur de la charte pour « Fond ».'];
        yield 'charte value written as hex' => ['fond', '#82CEC8', 'magenta', '« #82CEC8 » est déjà une valeur de la charte pour « Fond ».'];
        yield 'charte spacing written in px' => ['basEspace', '32px', 's', '« 32px » est déjà une valeur de la charte pour « Espace en dessous ».'];
        yield 'free value as new value' => ['fond', '#ff7700', '#a33c87', '« #a33c87 » n\'est pas une valeur de la charte pour « Fond ».'];
        yield 'key of another kind' => ['fond', '#ff7700', 'xl', '« xl » n\'est pas une valeur de la charte pour « Fond ».'];
        yield 'colour for a spacing' => ['hautEspace', '120px', 'magenta', '« magenta » n\'est pas une valeur de la charte pour « Espace au-dessus ».'];
        yield 'empty new value' => ['fond', '#ff7700', '', '«  » n\'est pas une valeur de la charte pour « Fond ».'];
    }

    public function testALongRefusedValueIsShortenedInTheMessage(): void
    {
        try {
            StyleReplacement::of('fond', str_repeat('a', 500), 'magenta');
            $this->fail('refused');
        } catch (InvalidStyleReplacement $exception) {
            $this->assertSame('« ' . str_repeat('a', 40) . '... » n\'est pas une valeur valide pour « Fond ».', $exception->getMessage());
        }
    }

    public function testTheBlocksHoldingTheValueOnThePropertyAreFound(): void
    {
        $replacement = StyleReplacement::of('fond', '#ff7700', 'magenta');

        $this->assertSame(['Bloc n°1', 'Bloc n°3', 'Bloc n°4'], $replacement->matches([
            ['type' => 'cta', 'props' => [], 'style' => ['fond' => '#ff7700']],
            ['type' => 'cta', 'props' => [], 'style' => ['texte' => '#ff7700']],
            ['type' => 'cta', 'props' => [], 'style' => ['fond' => '#FF7700', 'alignement' => 'centre']],
            ['type' => 'cta', 'props' => [], 'style' => ['fond' => '#ff7700']],
            ['type' => 'cta', 'props' => []],
            ['type' => 'cta', 'props' => [], 'style' => ['fond' => '#ff7701']],
        ]));
        $this->assertSame([], $replacement->matches([]));
    }

    public function testAStoredContentThatIsNotTextNeverFailsAndHoldsNothing(): void
    {
        $replacement = StyleReplacement::of('fond', '#ff7700', 'magenta');
        $blocks = [
            ['type' => 'cta', 'props' => [], 'style' => ['fond' => 5]],
            ['type' => 'cta', 'props' => [], 'style' => ['fond' => ['#ff7700']]],
            ['type' => 'cta', 'props' => [], 'style' => ['fond' => true]],
            ['type' => 'cta', 'props' => [], 'style' => ['fond' => null]],
            ['type' => 'cta', 'props' => [], 'style' => 'fond'],
            ['type' => 'cta', 'props' => [], 'style' => 12],
            'bloc',
            7,
            null,
            ['props' => [], 'style' => ['fond' => '#ff7700']],
            ['type' => 5, 'props' => [], 'style' => ['fond' => '#ff7700']],
            ['type' => 'cta', 'props' => 'x', 'style' => ['fond' => '#ff7700']],
        ];

        $this->assertSame(['Bloc n°12'], $replacement->matches($blocks));
        $expected = $blocks;
        $expected[11]['style'] = ['fond' => 'magenta'];
        $this->assertSame($expected, $replacement->apply($blocks));
    }

    public function testStoredBlocksUnderOtherKeysAreCountedInOrderAndKeepTheirKeys(): void
    {
        $replacement = StyleReplacement::of('fond', '#ff7700', 'magenta');
        $blocks = [
            'a' => ['type' => 'cta', 'props' => []],
            'b' => ['type' => 'cta', 'props' => [], 'style' => ['fond' => '#ff7700', 'texte' => '#123456']],
        ];

        $this->assertSame(['Bloc n°2'], $replacement->matches($blocks));
        $this->assertSame([
            'a' => ['type' => 'cta', 'props' => []],
            'b' => ['type' => 'cta', 'props' => [], 'style' => ['fond' => 'magenta', 'texte' => '#123456']],
        ], $replacement->apply($blocks));
    }

    public function testTheBlocksOfTheContainersAreFoundAndReplacedInPlace(): void
    {
        $replacement = StyleReplacement::of('fond', '#ff7700', 'magenta');
        $blocks = [
            ['type' => 'section', 'props' => [], 'style' => ['fond' => '#ff7700'], 'children' => [
                ['type' => 'faq', 'props' => [], 'style' => []],
                ['type' => 'colonnes', 'props' => [], 'style' => [], 'columns' => [
                    [['type' => 'faq', 'props' => [], 'style' => ['fond' => '#ff7700']]],
                    'colonne',
                    [['type' => 'faq', 'props' => [], 'style' => ['fond' => '#FF7700']]],
                ]],
            ]],
        ];

        $this->assertSame(['Section 1', 'Section 1 › colonnes 2 › colonne 1 › bloc 1', 'Section 1 › colonnes 2 › colonne 3 › bloc 1'], $replacement->matches($blocks));
        $expected = $blocks;
        $expected[0]['style'] = ['fond' => 'magenta'];
        $expected[0]['children'][1]['columns'][0][0]['style'] = ['fond' => 'magenta'];
        $expected[0]['children'][1]['columns'][2][0]['style'] = ['fond' => 'magenta'];
        $this->assertSame($expected, $replacement->apply($blocks));
    }

    public function testApplyChangesTheValueOnlyAndKeepsTheRestOfTheContent(): void
    {
        $replacement = StyleReplacement::of('hautEspace', '120px', 'xl');

        $this->assertSame([
            ['type' => 'cta', 'props' => ['title' => 'A'], 'style' => ['hautEspace' => 'xl', 'alignement' => 'centre']],
            ['type' => 'faq', 'props' => ['title' => 'B'], 'style' => ['basEspace' => '120px']],
            ['type' => 'faq', 'props' => ['title' => 'C']],
        ], $replacement->apply([
            ['type' => 'cta', 'props' => ['title' => 'A'], 'style' => ['hautEspace' => '120px', 'alignement' => 'centre']],
            ['type' => 'faq', 'props' => ['title' => 'B'], 'style' => ['basEspace' => '120px']],
            ['type' => 'faq', 'props' => ['title' => 'C']],
        ]));
    }
}
