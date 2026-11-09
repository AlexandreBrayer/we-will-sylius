<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Delete;
use App\Entity\Content\CmsSection;
use App\Tests\Yack\Content\Application\InMemoryCmsSections;
use App\Yack\Content\Infrastructure\Api\CmsSectionDeleteProcessor;
use App\Yack\Content\Infrastructure\Api\CmsSectionView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
#[CoversClass(CmsSectionDeleteProcessor::class)]
final class CmsSectionDeleteProcessorTest extends TestCase
{
    public function testOnlyTheSectionIsRemoved(): void
    {
        $sections = new InMemoryCmsSections();
        $sections->add($kept = new CmsSection('Gardée', [['type' => 'cta', 'props' => [], 'style' => []]]));
        $sections->add($removed = new CmsSection('Supprimée', [['type' => 'cta', 'props' => [], 'style' => []]]));

        (new CmsSectionDeleteProcessor($sections))->process(CmsSectionView::of($removed), new Delete());

        $this->assertSame([$kept], $sections->all());
    }

    public function testASectionDeletedMeanwhileIsNotFound(): void
    {
        $sections = new InMemoryCmsSections();
        $sections->add($section = new CmsSection('Bandeau', [['type' => 'cta', 'props' => [], 'style' => []]]));
        $view = CmsSectionView::of($section);
        $sections->sections = [];

        $this->expectException(NotFoundHttpException::class);

        (new CmsSectionDeleteProcessor($sections))->process($view, new Delete());
    }
}
