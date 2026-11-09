<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Put;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Content\CmsSection;
use App\Tests\Yack\Content\Application\InMemoryCmsSections;
use App\Yack\Content\Infrastructure\Api\CmsSectionRenameProcessor;
use App\Yack\Content\Infrastructure\Api\CmsSectionView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PUT as API Platform runs it: the body is written into the view given by the provider, then processed.
 *
 * @internal
 */
#[CoversClass(CmsSectionRenameProcessor::class)]
final class CmsSectionRenameProcessorTest extends TestCase
{
    private const BLOCKS = [['type' => 'texte-image', 'props' => ['title' => 'Orange'], 'style' => ['fond' => '#ff7700']]];

    public function testOnlyTheNameChanges(): void
    {
        [$sections, $section, $view] = self::stored();
        $view->name = " Promo\nde printemps ";
        $view->blocks = [];

        $saved = (new CmsSectionRenameProcessor($sections))->process($view, new Put(), ['id' => 1]);

        $this->assertSame(['Promo de printemps', self::BLOCKS, 1], [$saved->name, $saved->blocks, $saved->blockCount]);
        $this->assertSame(['Promo de printemps', self::BLOCKS], [$section->getName(), $section->getBlocks()]);
        $this->assertSame(1, $sections->saves);
    }

    public function testAnEmptyNameIsA422AndTheSectionKeepsItsName(): void
    {
        [$sections, $section, $view] = self::stored();
        $view->name = '   ';

        try {
            (new CmsSectionRenameProcessor($sections))->process($view, new Put());
            $this->fail('An empty name must be refused.');
        } catch (ValidationException $refused) {
            $this->assertSame('name: Donnez un nom à la section.', $refused->getMessage());
        }

        $this->assertSame(['Bandeau', 0], [$section->getName(), $sections->saves]);
    }

    public function testASectionDeletedMeanwhileIsNotFound(): void
    {
        [$sections, , $view] = self::stored();
        $sections->sections = [];

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Section introuvable.');

        (new CmsSectionRenameProcessor($sections))->process($view, new Put());
    }

    /**
     * @return array{InMemoryCmsSections, CmsSection, CmsSectionView}
     */
    private static function stored(): array
    {
        $sections = new InMemoryCmsSections();
        $section = new CmsSection('Bandeau', self::BLOCKS);
        $sections->add($section);

        return [$sections, $section, CmsSectionView::of($section)];
    }
}
