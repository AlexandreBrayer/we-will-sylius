<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Yack\Content\Domain\BlockStyle;
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
            'fond' => 'plum', 'texte' => 'white', 'accent' => 'yellow', 'titre' => 'xl', 'hautEspace' => 'xl', 'basEspace' => '0',
            'largeur' => 'pleine', 'alignement' => 'centre', 'arrondi' => 'moyen', 'masquerMobile' => 'oui', 'masquerOrdinateur' => 'non', 'ancre' => 'nos-services-2',
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
        $this->assertSame([], BlockStyle::choices('fond'));
    }
}
