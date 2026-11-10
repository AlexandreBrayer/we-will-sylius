<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Yack\Content\Domain\BlockStyle;
use App\Yack\Content\Domain\BlockType;
use App\Yack\Content\Domain\Charte;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BlockType::class)]
final class BlockTypeTest extends TestCase
{
    public function testTheImagePropsAreExactlyThePropsThatOfferTheHistoricalVisuals(): void
    {
        foreach (BlockType::cases() as $type) {
            $offering = array_keys(array_filter($type->props(), static fn (?array $choices): bool => null !== $choices && [] === array_diff(BlockType::IMAGES, $choices)));

            $this->assertSame($offering, $type->imageProps(), $type->value);
        }
    }

    public function testTheImagePropsOfTheBlocksWithVisuals(): void
    {
        $this->assertSame(['image'], BlockType::Hero->imageProps());
        $this->assertSame(['image'], BlockType::TextImage->imageProps());
        $this->assertSame(['image1', 'image2', 'image3', 'image4', 'image5'], BlockType::Tiles->imageProps());
        $this->assertSame(['image1', 'image2', 'image3'], BlockType::Support->imageProps());
        $this->assertSame([], BlockType::Faq->imageProps());
    }

    public function testTheTwentyOneHistoricalVisualsAreFilesOfThePublicDir(): void
    {
        $this->assertCount(21, BlockType::IMAGES);
        foreach (BlockType::IMAGES as $path) {
            $this->assertFileExists(\dirname(__DIR__, 4) . '/public/' . $path);
        }
    }

    public function testEveryImagePropHasItsAlternativeTextFramingAndFocalPoint(): void
    {
        $this->assertSame(
            ['imageAlt' => null, 'imageFit' => ['', 'couvrir', 'contenir'], 'imageFocus' => ['', 'haut', 'centre', 'bas']],
            BlockType::TextImage->imageSettingProps(),
        );
        $this->assertSame(
            ['image1Alt', 'image1Fit', 'image1Focus', 'image2Alt', 'image2Fit', 'image2Focus', 'image3Alt', 'image3Fit', 'image3Focus'],
            array_keys(BlockType::Support->imageSettingProps()),
        );
        $this->assertSame([], BlockType::Faq->imageSettingProps());
        foreach (BlockType::cases() as $type) {
            $this->assertSame($type->imageSettingProps(), array_intersect_key($type->props(), $type->imageSettingProps()), $type->value);
            $this->assertCount(3 * count($type->imageProps()), $type->imageSettingProps(), $type->value);
        }
    }

    public function testAnAlternativeTextIsOneLineWithoutHtmlAndAsShortAsTheOneOfTheLibrary(): void
    {
        $this->assertSame(['image1Alt', 'image2Alt', 'image3Alt'], BlockType::Support->imageAltProps());
        $this->assertSame([], BlockType::Faq->imageAltProps());
        $this->assertTrue(BlockType::isImageAlt(''));
        $this->assertTrue(BlockType::isImageAlt('Salon « chauffé » & climatisé'));
        $this->assertTrue(BlockType::isImageAlt(str_repeat('é', 255)));
        $this->assertFalse(BlockType::isImageAlt(str_repeat('é', 256)));
        $this->assertFalse(BlockType::isImageAlt('<b>gras</b>'));
        $this->assertFalse(BlockType::isImageAlt('a > b'));
        $this->assertFalse(BlockType::isImageAlt("Deux\nlignes"));
        $this->assertFalse(BlockType::isImageAlt("tab\tulation"));
        $this->assertFalse(BlockType::isImageAlt("\xff\xfe"));
    }

    public function testAnImageWithoutSettingsKeepsTheFramingOfItsBlock(): void
    {
        $this->assertSame('', BlockType::IMAGE_FITS[0]);
        $this->assertSame('', BlockType::IMAGE_FOCUSES[0]);
    }

    public function testTheLinkPropsAreTheCtaAndSlotUrls(): void
    {
        $links = [];
        foreach (BlockType::cases() as $type) {
            if ([] !== $type->linkProps()) {
                $links[$type->value] = $type->linkProps();
            }
        }

        $this->assertSame([
            'hero' => ['ctaUrl', 'cta2Url'],
            'texte-image' => ['ctaUrl'],
            'cta' => ['ctaUrl'],
            'acces-rapides' => ['url1', 'url2', 'url3', 'url4'],
            'tuiles' => ['url1', 'url2', 'url3', 'url4', 'url5'],
            'accompagnement' => ['url1', 'url2', 'url3'],
        ], $links);
    }

    public function testTheLinkPropsAreFreeTextLikeTheLinkFieldOfTheBuilder(): void
    {
        foreach (BlockType::cases() as $type) {
            foreach ($type->linkProps() as $name) {
                $this->assertNull($type->props()[$name], $type->value . '.' . $name);
            }
        }
    }

    public function testTheLinkPropPatternIsTheOneOfTheBuilder(): void
    {
        $registry = (string) file_get_contents(\dirname(__DIR__, 4) . '/front/src/lib/blocks/registry.ts');

        preg_match('~^export const LINK_PROP = (/.+/);$~m', $registry, $match);

        $this->assertSame(($match[1] ?? 'absent de registry.ts') . 'D', BlockType::LINK_PROP_PATTERN);
    }

    public function testOnlySectionAndColumnsAreContainers(): void
    {
        $this->assertSame(
            ['section', 'colonnes'],
            array_values(array_map(static fn (BlockType $type): string => $type->value, array_filter(BlockType::cases(), static fn (BlockType $type): bool => $type->isContainer()))),
        );
        $this->assertSame(['Section', 'Colonnes'], [BlockType::Section->label(), BlockType::Columns->label()]);
        $this->assertNull(BlockType::Section->scope());
    }

    public function testTheFullWidthBlocksAreFreeContentBlocks(): void
    {
        foreach (BlockType::FULL_WIDTH as $value) {
            $type = BlockType::from($value);
            $this->assertTrue($type->isFullWidth(), $value);
            $this->assertFalse($type->isContainer(), $value);
            $this->assertNull($type->scope(), $value);
        }
        $this->assertFalse(BlockType::Faq->isFullWidth());
    }

    public function testWhereEachBlockMayGo(): void
    {
        foreach (BlockType::cases() as $type) {
            $this->assertTrue($type->allowedIn(null), $type->value . ' à la racine');
        }
        $this->assertSame([true, true, true, false, false], [
            BlockType::Columns->allowedIn(BlockType::Section),
            BlockType::Hero->allowedIn(BlockType::Section),
            BlockType::Faq->allowedIn(BlockType::Section),
            BlockType::Section->allowedIn(BlockType::Section),
            BlockType::ProductBuyBox->allowedIn(BlockType::Section),
        ]);
        $this->assertSame([true, true, false, false, false, false], [
            BlockType::Faq->allowedIn(BlockType::Columns),
            BlockType::Support->allowedIn(BlockType::Columns),
            BlockType::Hero->allowedIn(BlockType::Columns),
            BlockType::Columns->allowedIn(BlockType::Columns),
            BlockType::Section->allowedIn(BlockType::Columns),
            BlockType::ProductDescription->allowedIn(BlockType::Columns),
        ]);
        $this->assertFalse(BlockType::Faq->allowedIn(BlockType::Faq), 'un bloc de contenu ne contient rien');
    }

    public function testTheColumnsPropsAndTheirNumberOfColumns(): void
    {
        $this->assertSame(['audience' => BlockType::AUDIENCES], BlockType::Section->props());
        $this->assertSame(
            ['ratio' => BlockType::COLUMN_RATIOS, 'alignement' => BlockType::COLUMN_ALIGNMENTS, 'espacement' => BlockType::COLUMN_GAPS, 'audience' => BlockType::AUDIENCES],
            BlockType::Columns->props(),
        );
        $this->assertSame([2, 2, 2, 3], array_map(BlockType::columnCount(...), BlockType::COLUMN_RATIOS));
        $gaps = BlockType::COLUMN_GAPS;
        $spacings = array_map('strval', array_keys(Charte::SPACINGS));
        sort($gaps);
        sort($spacings);
        $this->assertSame($spacings, $gaps, 'the gaps are the spacings of the charte');
        $this->assertSame([], BlockType::Columns->imageProps());
        $this->assertSame([], BlockType::Section->linkProps());
    }

    public function testAContainerTakesOnlyItsOwnStyleProperties(): void
    {
        $this->assertSame(BlockType::CONTAINER_STYLE, BlockType::Section->styleProperties());
        $this->assertSame(BlockType::CONTAINER_STYLE, BlockType::Columns->styleProperties());
        $this->assertSame(array_keys(BlockStyle::PROPERTIES), BlockType::Faq->styleProperties());
        $this->assertSame([], array_diff(BlockType::CONTAINER_STYLE, array_keys(BlockStyle::PROPERTIES)));
    }

    public function testTheTitleBlockIsAFreeBlockWithAHeadingAndASubtitle(): void
    {
        $this->assertSame('Titre', BlockType::Title->label());
        $this->assertSame(['titre' => null, 'sousTitre' => null, 'audience' => BlockType::AUDIENCES], BlockType::Title->props());
        $this->assertNull(BlockType::Title->scope());
        $this->assertTrue(BlockType::Title->allowedIn(BlockType::Columns));
        $this->assertSame(array_keys(BlockStyle::PROPERTIES), BlockType::Title->styleProperties());
        $this->assertSame(['titre'], BlockType::Title->requiredProps());
    }

    public function testShortAndRequiredPropsAreFreeTextPropsOfTheirBlock(): void
    {
        $short = [];
        foreach (BlockType::cases() as $type) {
            foreach ([...$type->shortTextProps(), ...$type->requiredProps()] as $name) {
                $this->assertArrayHasKey($name, $type->props(), $type->value . '.' . $name);
                $this->assertNull($type->props()[$name], $type->value . '.' . $name);
            }
            if ([] !== $type->shortTextProps()) {
                $short[$type->value] = $type->shortTextProps();
            }
        }

        $this->assertSame(['titre' => ['titre', 'sousTitre']], $short);
        $this->assertSame([], BlockType::Faq->requiredProps());
    }
}
