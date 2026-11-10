<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Yack\Content\Domain\BlockPath;
use App\Yack\Content\Domain\BlockStyle;
use App\Yack\Content\Domain\BlockType;
use App\Yack\Content\Domain\BuilderLevel;
use App\Yack\Content\Domain\InvalidPageBlocks;
use App\Yack\Content\Domain\StyleDeviation;
use App\Yack\Content\Domain\StyleNotAllowed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BlockStyle::class)]
final class BlockStyleTest extends TestCase
{
    public function testAbsentStyleIsEmpty(): void
    {
        $this->assertSame([], BlockStyle::normalize(null, BuilderLevel::Charte));
        $this->assertSame([], BlockStyle::normalize([], BuilderLevel::Charte));
    }

    public function testCharteStyleComesOutAsIsAtEveryLevel(): void
    {
        $style = [
            'fond' => 'plum', 'texte' => 'white', 'accent' => 'yellow', 'balise' => 'h1', 'titre' => 'xl', 'hautEspace' => 'xl', 'basEspace' => '0',
            'gaucheEspace' => 'm', 'droiteEspace' => 'l', 'largeur' => 'pleine', 'alignement' => 'centre', 'alignementVertical' => 'bas', 'arrondi' => 'moyen', 'masquerMobile' => 'oui', 'masquerOrdinateur' => 'non', 'ancre' => 'nos-services-2',
        ];

        foreach (BuilderLevel::cases() as $level) {
            $this->assertSame($style, BlockStyle::normalize($style, $level), $level->value);
        }
    }

    public function testPropertiesComeOutInTheirOrderAndEmptyValuesAreDropped(): void
    {
        $this->assertSame(
            ['fond' => 'teal', 'alignement' => 'droite'],
            BlockStyle::normalize(['alignement' => 'droite', 'texte' => '', 'fond' => 'teal'], BuilderLevel::Charte),
        );
    }

    public function testFreeValuesAreAcceptedWhenTheLevelAllowsThem(): void
    {
        $style = ['fond' => '#FF7700', 'titre' => '40px', 'hautEspace' => '120px', 'basEspace' => '0px'];

        foreach ([BuilderLevel::LibreAvertissement, BuilderLevel::Libre] as $level) {
            $this->assertSame(['fond' => '#ff7700', 'titre' => 'l', 'hautEspace' => '120px', 'basEspace' => '0'], BlockStyle::normalize($style, $level));
        }
    }

    public function testFreeValueEqualToACharteValueBecomesItsKey(): void
    {
        $this->assertSame(['fond' => 'teal', 'titre' => 'm', 'hautEspace' => 's'], BlockStyle::normalize(['fond' => '#82CEC8', 'titre' => '32px', 'hautEspace' => '16px'], BuilderLevel::Charte));
    }

    #[DataProvider('provideTitleTagsAndVerticalAlignments')]
    public function testTitleTagAndVerticalAlignmentTakeTheirChoicesAtEveryLevel(string $property, string $value): void
    {
        foreach (BuilderLevel::cases() as $level) {
            $this->assertSame([$property => $value], BlockStyle::normalize([$property => $value], $level), $level->value);
        }
    }

    /**
     * @return \Iterator<string, array{string, string}>
     */
    public static function provideTitleTagsAndVerticalAlignments(): \Iterator
    {
        foreach (['h1', 'h2', 'h3', 'p'] as $tag) {
            yield 'balise ' . $tag => ['balise', $tag];
        }
        foreach (['haut', 'milieu', 'bas'] as $alignment) {
            yield 'alignement vertical ' . $alignment => ['alignementVertical', $alignment];
        }
    }

    #[DataProvider('provideSpacingValues')]
    public function testLateralSpacingsAcceptExactlyWhatTheSpacingAboveAccepts(string $value, string $expected): void
    {
        foreach (['hautEspace', 'gaucheEspace', 'droiteEspace'] as $property) {
            $this->assertSame([$property => $expected], BlockStyle::normalize([$property => $value], BuilderLevel::Libre), $property);
        }
    }

    /**
     * @return \Iterator<string, array{string, string}>
     */
    public static function provideSpacingValues(): \Iterator
    {
        foreach (['0', 's', 'm', 'l', 'xl'] as $key) {
            yield 'charte ' . $key => [$key, $key];
        }
        yield 'charte value in px' => ['16px', 's'];
        yield 'zero in px' => ['0px', '0'];
        yield 'free value' => ['120px', '120px'];
        yield 'upper bound' => ['200px', '200px'];
    }

    #[DataProvider('provideRefusedSpacings')]
    public function testLateralSpacingsRefuseExactlyWhatTheSpacingAboveRefuses(mixed $value): void
    {
        foreach (['hautEspace', 'gaucheEspace', 'droiteEspace'] as $property) {
            try {
                BlockStyle::normalize([$property => $value], BuilderLevel::Libre, 2);
                $this->fail(sprintf('« %s » accepté pour %s.', var_export($value, true), $property));
            } catch (InvalidPageBlocks $exception) {
                $this->assertStringContainsString(sprintf('« %s »', BlockStyle::label($property)), $exception->getMessage(), $property);
            }
        }
    }

    /**
     * @return \Iterator<string, array{mixed}>
     */
    public static function provideRefusedSpacings(): \Iterator
    {
        yield 'too large' => ['201px'];
        yield 'negative' => ['-8px'];
        yield 'leading zero' => ['016px'];
        yield 'rem' => ['2rem'];
        yield 'unknown key' => ['xs'];
        yield 'trailing newline' => ["120px\n"];
        yield 'number' => [120];
        yield 'null' => [null];
    }

    #[DataProvider('provideFreeValues')]
    public function testCharteLevelRefusesAFreeValue(string $property, string $value, string $message): void
    {
        $this->expectException(StyleNotAllowed::class);
        $this->expectExceptionMessage($message);

        BlockStyle::normalize([$property => $value], BuilderLevel::Charte, 3);
    }

    /**
     * @return \Iterator<string, array{string, string, string}>
     */
    public static function provideFreeValues(): \Iterator
    {
        yield 'colour' => ['accent', '#ff7700', 'Bloc n°3 : la couleur #ff7700 est hors charte. Votre compte ne permet pas de sortir de la charte.'];
        yield 'title size' => ['titre', '44px', 'Bloc n°3 : la taille de titre 44px est hors charte.'];
        yield 'spacing' => ['basEspace', '120px', 'Bloc n°3 : l\'espacement 120px est hors charte.'];
        yield 'left spacing' => ['gaucheEspace', '120px', 'Bloc n°3 : l\'espacement 120px est hors charte. Votre compte ne permet pas de sortir de la charte.'];
        yield 'right spacing' => ['droiteEspace', '8px', 'Bloc n°3 : l\'espacement 8px est hors charte.'];
    }

    #[DataProvider('provideMalformedStyles')]
    public function testMalformedStyleIsRefusedWhateverTheLevel(mixed $style, string $message): void
    {
        foreach (BuilderLevel::cases() as $level) {
            try {
                BlockStyle::normalize($style, $level, 2);
                $this->fail(sprintf('Style accepté en niveau %s.', $level->value));
            } catch (InvalidPageBlocks $exception) {
                $this->assertStringContainsString($message, $exception->getMessage(), $level->value);
            }
        }
    }

    /**
     * @return \Iterator<string, array{mixed, string}>
     */
    public static function provideMalformedStyles(): \Iterator
    {
        yield 'not a map' => ['color:red', 'Bloc n°2 : style invalide.'];
        yield 'a list' => [['teal'], 'Bloc n°2 : style invalide.'];
        yield 'unknown property' => [['css' => 'color:red'], 'Bloc n°2 : réglage(s) de style non autorisé(s) : css.'];
        yield 'not a string' => [['masquerMobile' => true], 'valeur invalide pour « Masquer sur mobile »'];
        yield 'null value' => [['fond' => null], 'Bloc n°2 : valeur invalide pour « Fond ».'];
        yield 'number' => [['hautEspace' => 120], 'Bloc n°2 : valeur invalide pour « Espace au-dessus ».'];
        yield 'nested array' => [['texte' => ['#ff7700']], 'Bloc n°2 : valeur invalide pour « Texte ».'];
        yield 'nested map' => [['accent' => ['valeur' => 'teal']], 'Bloc n°2 : valeur invalide pour « Accent ».'];
        yield 'colour name' => [['fond' => 'red'], '« red » n\'est pas une couleur valide pour « Fond »'];
        yield 'short hexadecimal' => [['texte' => '#f70'], '« #f70 » n\'est pas une couleur valide pour « Texte »'];
        yield 'css function' => [['fond' => 'url(javascript:alert(1))'], 'n\'est pas une couleur valide'];
        yield 'title too large' => [['titre' => '200px'], '« 200px » n\'est pas une taille de titre valide (une taille de la charte, ou de 14px à 96px).'];
        yield 'title too small' => [['titre' => '12px'], 'n\'est pas une taille de titre valide'];
        yield 'title in rem' => [['titre' => '2rem'], 'n\'est pas une taille de titre valide'];
        yield 'title leading zero' => [['titre' => '032px'], 'n\'est pas une taille de titre valide'];
        yield 'spacing too large' => [['hautEspace' => '201px'], '« 201px » n\'est pas un espacement valide pour « Espace au-dessus »'];
        yield 'negative spacing' => [['basEspace' => '-8px'], 'n\'est pas un espacement valide'];
        yield 'unknown width' => [['largeur' => 'immense'], '« immense » n\'est pas une valeur autorisée pour « Largeur »'];
        yield 'unknown alignment' => [['alignement' => 'justifie'], 'n\'est pas une valeur autorisée pour « Alignement »'];
        yield 'tag h4' => [['balise' => 'h4'], '« h4 » n\'est pas une valeur autorisée pour « Balise »'];
        yield 'tag in upper case' => [['balise' => 'H1'], 'n\'est pas une valeur autorisée pour « Balise »'];
        yield 'tag div' => [['balise' => 'div'], 'n\'est pas une valeur autorisée pour « Balise »'];
        yield 'tag as markup' => [['balise' => '<h1>'], 'n\'est pas une valeur autorisée pour « Balise »'];
        yield 'tag not a string' => [['balise' => 1], 'Bloc n°2 : valeur invalide pour « Balise ».'];
        yield 'vertical alignment of the horizontal one' => [['alignementVertical' => 'centre'], '« centre » n\'est pas une valeur autorisée pour « Alignement vertical »'];
        yield 'vertical alignment in english' => [['alignementVertical' => 'bottom'], 'n\'est pas une valeur autorisée pour « Alignement vertical »'];
        yield 'free radius' => [['arrondi' => '8px'], '« 8px » n\'est pas une valeur autorisée pour « Arrondi »'];
        yield 'unknown flag' => [['masquerOrdinateur' => 'peut-etre'], 'n\'est pas une valeur autorisée pour « Masquer sur ordinateur »'];
        yield 'anchor with a space' => [['ancre' => 'nos services'], '« nos services » n\'est pas une ancre valide'];
        yield 'anchor in upper case' => [['ancre' => 'Contact'], 'n\'est pas une ancre valide'];
        yield 'anchor too long' => [['ancre' => str_repeat('a', 41)], 'n\'est pas une ancre valide'];
        yield 'colour with a trailing newline' => [['fond' => "#ff7700\n"], 'n\'est pas une couleur valide'];
        yield 'title with a trailing newline' => [['titre' => "40px\n"], 'n\'est pas une taille de titre valide'];
        yield 'spacing with a trailing newline' => [['hautEspace' => "120px\n"], 'n\'est pas un espacement valide'];
        yield 'anchor with a trailing newline' => [['ancre' => "faq\n"], 'n\'est pas une ancre valide'];
    }

    public function testFormatIsCheckedBeforeTheLevel(): void
    {
        $this->expectException(InvalidPageBlocks::class);

        BlockStyle::normalize(['fond' => '#ff7700', 'titre' => '200px'], BuilderLevel::Charte);
    }

    public function testDeviationsListEveryFreeValueAndNoCharteValue(): void
    {
        $deviations = BlockStyle::deviations(['fond' => '#ff7700', 'texte' => 'white', 'titre' => '44px', 'hautEspace' => 'm', 'basEspace' => '120px', 'largeur' => 'pleine', 'ancre' => 'faq'], 4);

        $this->assertContainsOnlyInstancesOf(StyleDeviation::class, $deviations);
        $this->assertSame(
            [[4, 'fond', '#ff7700'], [4, 'titre', '44px'], [4, 'basEspace', '120px']],
            array_map(static fn (StyleDeviation $deviation): array => [$deviation->block, $deviation->property, $deviation->value], $deviations),
        );
        $this->assertSame([], BlockStyle::deviations(['fond' => 'teal', 'titre' => 's', 'basEspace' => '0', 'arrondi' => 'grand']));
    }

    public function testTitleTagAndVerticalAlignmentAreNeverDeviationsLateralFreeSpacingsAre(): void
    {
        $deviations = BlockStyle::deviations(['balise' => 'h1', 'gaucheEspace' => '120px', 'droiteEspace' => 'l', 'alignementVertical' => 'bas'], 2);

        $this->assertSame(
            [[2, 'gaucheEspace', '120px']],
            array_map(static fn (StyleDeviation $deviation): array => [$deviation->block, $deviation->property, $deviation->value], $deviations),
        );
        // Stored as is, even malformed: a choice is never counted, whatever its value.
        $this->assertSame([], BlockStyle::deviations(['balise' => 'div', 'alignementVertical' => 'x', 'droiteEspace' => 's']));
        $this->assertSame(['balise' => 'p', 'droiteEspace' => 'xl', 'alignementVertical' => 'milieu'], BlockStyle::normalize(['alignementVertical' => 'milieu', 'droiteEspace' => '80px', 'balise' => 'p'], BuilderLevel::Charte));
    }

    public function testAContainerTakesItsOwnPropertiesAndMessagesNameThePath(): void
    {
        $path = BlockPath::root(2, BlockType::Section)->child(1, BlockType::Columns);

        $this->assertSame(['fond' => 'light', 'largeur' => 'large'], BlockStyle::normalize(['fond' => 'light', 'largeur' => 'large'], BuilderLevel::Charte, $path, BlockType::Columns));

        try {
            BlockStyle::normalize(['arrondi' => 'grand'], BuilderLevel::Libre, $path, BlockType::Columns);
            $this->fail('Arrondi accepté sur des colonnes.');
        } catch (InvalidPageBlocks $exception) {
            $this->assertSame('Section 2 › colonnes 1 : réglage(s) de style non autorisé(s) : arrondi.', $exception->getMessage());
        }

        try {
            BlockStyle::normalize(['fond' => '#ff7700'], BuilderLevel::Charte, $path, BlockType::Columns);
            $this->fail('Couleur libre acceptée en niveau charte.');
        } catch (StyleNotAllowed $refused) {
            $this->assertSame([2, 'Section 2 › colonnes 1', 'colonnes'], [$refused->deviation->block, $refused->deviation->where, $refused->deviation->type]);
        }
    }

    public function testAContainerTakesTheLateralSpacingsButNeitherTheTitleTagNorTheVerticalAlignment(): void
    {
        $this->assertSame(['gaucheEspace' => 'm', 'droiteEspace' => '120px'], BlockStyle::normalize(['droiteEspace' => '120px', 'gaucheEspace' => '32px'], BuilderLevel::Libre, 1, BlockType::Section));

        foreach (['balise' => 'h1', 'alignementVertical' => 'bas'] as $property => $value) {
            try {
                BlockStyle::normalize([$property => $value], BuilderLevel::Libre, 1, BlockType::Columns);
                $this->fail(sprintf('%s accepté sur des colonnes.', $property));
            } catch (InvalidPageBlocks $exception) {
                $this->assertSame(sprintf('Bloc n°1 : réglage(s) de style non autorisé(s) : %s.', $property), $exception->getMessage());
            }
        }
    }

    /**
     * @param list<array{string, string}> $expected
     */
    #[DataProvider('provideMalformedStoredStyles')]
    public function testDeviationsIgnoreWhatIsNotAStringInAStoredStyle(mixed $style, array $expected): void
    {
        $this->assertSame(
            $expected,
            array_map(static fn (StyleDeviation $deviation): array => [$deviation->property, $deviation->value], BlockStyle::deviations($style, 2)),
        );
    }

    /**
     * @return \Iterator<string, array{mixed, list<array{string, string}>}>
     */
    public static function provideMalformedStoredStyles(): \Iterator
    {
        yield 'number next to a free value' => [['fond' => 5, 'texte' => '#ff7700'], [['texte', '#ff7700']]];
        yield 'float' => [['titre' => 40.5], []];
        yield 'null' => [['fond' => null], []];
        yield 'boolean' => [['accent' => true], []];
        yield 'nested array' => [['fond' => ['#ff7700']], []];
        yield 'nested map' => [['hautEspace' => ['px' => '120px']], []];
        yield 'list' => [['#ff7700'], []];
        yield 'string' => ['fond', []];
        yield 'number' => [12, []];
        yield 'null style' => [null, []];
        yield 'unknown property' => [['css' => '#ff7700'], []];
        yield 'malformed string, still a deviation to correct' => [['fond' => 'red'], [['fond', 'red']]];
    }

    public function testLabelsKindsAndChoices(): void
    {
        $this->assertSame('Espace au-dessus', BlockStyle::label('hautEspace'));
        $this->assertSame('inconnu', BlockStyle::label('inconnu'));
        $this->assertSame(BlockStyle::KIND_COLOR, BlockStyle::kind('accent'));
        $this->assertSame(BlockStyle::KIND_ANCHOR, BlockStyle::kind('ancre'));
        $this->assertSame(BlockStyle::KIND_CHOICE, BlockStyle::kind('inconnu'));
        $this->assertSame(['contenu', 'large', 'pleine'], BlockStyle::choices('largeur'));
        $this->assertSame(['gauche', 'centre', 'droite'], BlockStyle::choices('alignement'));
        $this->assertSame(['aucun', 'petit', 'moyen', 'grand'], BlockStyle::choices('arrondi'));
        $this->assertSame(['oui', 'non'], BlockStyle::choices('masquerMobile'));
        $this->assertSame(['h1', 'h2', 'h3', 'p'], BlockStyle::choices('balise'));
        $this->assertSame(['haut', 'milieu', 'bas'], BlockStyle::choices('alignementVertical'));
        $this->assertSame('Espace à gauche', BlockStyle::label('gaucheEspace'));
        $this->assertSame('Espace à droite', BlockStyle::label('droiteEspace'));
        $this->assertSame(BlockStyle::KIND_SPACING, BlockStyle::kind('gaucheEspace'));
        $this->assertSame(BlockStyle::KIND_SPACING, BlockStyle::kind('droiteEspace'));
        $this->assertSame(BlockStyle::KIND_CHOICE, BlockStyle::kind('balise'));
        $this->assertSame(BlockStyle::KIND_CHOICE, BlockStyle::kind('alignementVertical'));
        $this->assertSame([], BlockStyle::choices('fond'));
    }
}
