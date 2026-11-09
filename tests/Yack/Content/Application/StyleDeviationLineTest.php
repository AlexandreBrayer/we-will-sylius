<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Application;

use App\Yack\Content\Application\StyleDeviationLine;
use App\Yack\Content\Domain\CmsRevisionTarget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(StyleDeviationLine::class)]
final class StyleDeviationLineTest extends TestCase
{
    #[DataProvider('provideSwatches')]
    public function testOnlyAWellFormedColourIsShownAsASwatch(string $property, string $value, ?string $swatch): void
    {
        $this->assertSame($swatch, self::line($property, $value)->color());
    }

    /**
     * @return \Iterator<string, array{string, string, ?string}>
     */
    public static function provideSwatches(): \Iterator
    {
        yield 'colour' => ['fond', '#ff7700', '#ff7700'];
        yield 'colour in upper case' => ['accent', '#FF7700', '#ff7700'];
        yield 'malformed colour' => ['fond', 'red', null];
        yield 'injection' => ['fond', '#ff7700;background:url(x)', null];
        yield 'trailing newline' => ['texte', "#ff7700\n", null];
        yield 'spacing' => ['hautEspace', '120px', null];
        yield 'size that looks like a colour' => ['titre', '#ff7700', null];
    }

    public function testThePropertyHasItsFrenchLabel(): void
    {
        $this->assertSame('Espace au-dessus', self::line('hautEspace', '120px')->propertyLabel());
    }

    public function testATemplateIsOpenedByItsIdentifierAndAPageByItsSlug(): void
    {
        $this->assertNull(self::line('fond', '#ff7700')->templateId());
        $template = new StyleDeviationLine(CmsRevisionTarget::Template, 'template:12', '12', 'Standard', 'Produit', 2, 'cta', 'Bandeau', 'fond', '#ff7700', null, null);
        $this->assertSame(12, $template->templateId());
        $this->assertFalse($template->hasRevision());
    }

    private static function line(string $property, string $value): StyleDeviationLine
    {
        return new StyleDeviationLine(CmsRevisionTarget::Page, 'page:demo', 'demo', 'Démo', null, 1, 'cta', 'Bandeau', $property, $value, null, new \DateTimeImmutable('2026-11-08 14:05'));
    }
}
