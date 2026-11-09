<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Application;

use App\Tests\Yack\Content\Infrastructure\Api\CmsMediaApi;
use App\Yack\Content\Application\CreateCmsSection;
use App\Yack\Content\Domain\BuilderLevel;
use App\Yack\Content\Domain\CmsSectionRefused;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CreateCmsSection::class)]
final class CreateCmsSectionTest extends TestCase
{
    private const NEW_FREE_VALUE = 'Bloc n°2 : la couleur #ff7700 est hors charte. Votre compte ne permet pas de sortir de la charte.';

    public function testTheSectionKeepsTheNormalizedBlocksWithTheirStyleAndItsAuthor(): void
    {
        $sections = new InMemoryCmsSections();

        $section = self::create($sections)(' Bandeau  promo ', [self::cta(), self::textImage('#FF7700')], BuilderLevel::Libre, 'Sophie Martin');

        $this->assertSame($section, $sections->find(1));
        $this->assertSame(['Bandeau promo', 'Sophie Martin', 2], [$section->getName(), $section->getAuthorName(), \count($section->getBlocks())]);
        $this->assertSame(['type' => 'cta', 'props' => ['kicker' => '', 'title' => 'Appel', 'text' => '', 'theme' => 'magenta', 'ctaLabel' => '', 'ctaUrl' => '', 'audience' => 'tous'], 'style' => []], $section->getBlocks()[0]);
        $this->assertSame(['fond' => '#ff7700', 'alignement' => 'centre'], $section->getBlocks()[1]['style']);
    }

    public function testACharteAccountSavesABlockOfTheCharte(): void
    {
        $sections = new InMemoryCmsSections();

        $section = self::create($sections)('Turquoise', [self::textImage('teal')], BuilderLevel::Charte, null);

        $this->assertSame([['fond' => 'teal', 'alignement' => 'centre'], null], [$section->getBlocks()[0]['style'], $section->getAuthorName()]);
    }

    /**
     * Same rule as a new page (CharteGuard, nothing stored before): every free value of a section is new.
     */
    public function testACharteAccountCannotSaveAFreeValueAndNothingIsWritten(): void
    {
        $sections = new InMemoryCmsSections();

        $this->assertRefused('blocks', self::NEW_FREE_VALUE, static fn () => self::create($sections)('Orange', [self::cta(), self::textImage('#ff7700')], BuilderLevel::Charte, 'Éditeur Démo'));
        $this->assertSame([], $sections->sections);
    }

    public function testAnAccountWithWarningSavesAFreeValue(): void
    {
        $section = self::create(new InMemoryCmsSections())('Orange', [self::textImage('#ff7700')], BuilderLevel::LibreAvertissement, null);

        $this->assertSame('#ff7700', $section->getBlocks()[0]['style']['fond'] ?? null);
    }

    public function testAnImageOfTheMediaLibraryIsAcceptedAndAnAbsentOneRefused(): void
    {
        $media = new InMemoryCmsMediaLibrary();
        $path = 'media/yack-cms/' . str_repeat('a', 32) . '.jpg';
        CmsMediaApi::add($media, $path, 'chantier.jpg', '2026-11-08');
        $create = new CreateCmsSection(new InMemoryCmsSections(), $media);

        $this->assertSame($path, $create('Photo', [['type' => 'texte-image', 'props' => ['image' => $path]]], BuilderLevel::Libre, null)->getBlocks()[0]['props']['image']);
        $this->assertRefused(
            'blocks',
            'Bloc n°1 (Texte + image) : « media/yack-cms/' . str_repeat('b', 32) . '.jpg » n\'est pas une valeur autorisée pour « image » : ni une image de la médiathèque, ni un visuel Yack.',
            static fn () => $create('Photo', [['type' => 'texte-image', 'props' => ['image' => 'media/yack-cms/' . str_repeat('b', 32) . '.jpg']]], BuilderLevel::Libre, null),
        );
    }

    /**
     * @return \Iterator<string, array{mixed, string}>
     */
    public static function refusedBlocks(): \Iterator
    {
        yield 'no block' => [[], 'Une section contient au moins un bloc.'];
        yield 'not a list' => [['a' => ['type' => 'cta', 'props' => []]], 'Le contenu doit être une liste de blocs.'];
        yield 'not an array' => ['cta', 'Le contenu doit être une liste de blocs.'];
        yield 'unknown type' => [[['type' => 'html', 'props' => []]], 'Bloc n°1 : type « html » inconnu.'];
        yield 'block of a product template' => [
            [self::cta(), ['type' => 'produit-galerie', 'props' => []]],
            'Bloc n°2 (Galerie produit) : un bloc lié à la fiche produit ne s\'enregistre pas comme section : seuls les blocs Yack libres le peuvent.',
        ];
        yield 'mandatory block of a category template' => [
            [['type' => 'categorie-produits', 'props' => []]],
            'Bloc n°1 (Liste des produits) : un bloc lié à la fiche catégorie ne s\'enregistre pas comme section : seuls les blocs Yack libres le peuvent.',
        ];
        yield 'link refused' => [[['type' => 'cta', 'props' => ['ctaUrl' => 'javascript:alert(1)']]], 'Bloc n°1 : le lien « javascript:alert(1) » n\'est pas une adresse autorisée.'];
        yield 'style out of its bounds' => [[['type' => 'cta', 'props' => [], 'style' => ['titre' => '300px']]], 'Bloc n°1 : « 300px » n\'est pas une taille de titre valide (une taille de la charte, ou de 14px à 96px).'];
        yield 'too many blocks' => [array_fill(0, 31, self::cta()), 'Une page contient au plus 30 blocs.'];
    }

    #[DataProvider('refusedBlocks')]
    public function testBlocksAreValidatedAsTheBlocksOfAPage(mixed $blocks, string $message): void
    {
        $sections = new InMemoryCmsSections();

        $this->assertRefused('blocks', $message, static fn () => self::create($sections)('Section', $blocks, BuilderLevel::Libre, null));
        $this->assertSame([], $sections->sections);
    }

    public function testTheNameIsCheckedFirst(): void
    {
        $this->assertRefused('name', 'Donnez un nom à la section.', static fn () => self::create(new InMemoryCmsSections())(" \n", [], BuilderLevel::Charte, null));
    }

    private static function create(InMemoryCmsSections $sections): CreateCmsSection
    {
        return new CreateCmsSection($sections, new InMemoryCmsMediaLibrary());
    }

    /**
     * @return array{type: string, props: array<string, string>}
     */
    private static function cta(): array
    {
        return ['type' => 'cta', 'props' => ['title' => 'Appel']];
    }

    /**
     * @return array{type: string, props: array<string, string>, style: array<string, string>}
     */
    private static function textImage(string $background): array
    {
        return ['type' => 'texte-image', 'props' => ['image' => 'yack/cms/hero.jpg'], 'style' => ['fond' => $background, 'alignement' => 'centre']];
    }

    private function assertRefused(string $field, string $message, callable $call): void
    {
        try {
            $call();
            $this->fail('The section must be refused.');
        } catch (CmsSectionRefused $refused) {
            $this->assertSame([$field, $message], [$refused->field, $refused->getMessage()]);
        }
    }
}
