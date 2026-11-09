<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Content\CmsSection;
use App\Tests\Yack\Content\Application\InMemoryCmsSections;
use App\Yack\Content\Infrastructure\Api\CmsSectionProvider;
use App\Yack\Content\Infrastructure\Api\CmsSectionView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CmsSectionProvider::class)]
final class CmsSectionProviderTest extends TestCase
{
    public function testTheListGivesEverySectionByName(): void
    {
        $sections = new InMemoryCmsSections();
        foreach (['Promo', 'appel', 'Bandeau'] as $name) {
            $sections->add(new CmsSection($name, [['type' => 'cta', 'props' => [], 'style' => []]]));
        }

        $list = (new CmsSectionProvider($sections))->provide(new GetCollection());

        $this->assertIsArray($list);
        $this->assertSame(['appel', 'Bandeau', 'Promo'], array_map(static fn (CmsSectionView $view): string => $view->name, $list));
    }

    public function testOneSectionByItsIdAndNullWhenUnknown(): void
    {
        $sections = new InMemoryCmsSections();
        $sections->add(new CmsSection('Bandeau', [['type' => 'cta', 'props' => [], 'style' => []]]));
        $provider = new CmsSectionProvider($sections);

        $one = $provider->provide(new Get(), ['id' => '1']);
        $this->assertInstanceOf(CmsSectionView::class, $one);
        $this->assertSame('Bandeau', $one->name);
        $this->assertNull($provider->provide(new Get(), ['id' => 2]));
        $this->assertNull($provider->provide(new Get(), ['id' => 'abc']));
        $this->assertNull($provider->provide(new Get()));
    }
}
