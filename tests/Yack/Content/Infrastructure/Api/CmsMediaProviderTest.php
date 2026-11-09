<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Tests\Yack\Content\Application\InMemoryCmsMediaLibrary;
use App\Yack\Content\Infrastructure\Api\CmsMediaProvider;
use App\Yack\Content\Infrastructure\Api\CmsMediaView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CmsMediaProvider::class)]
final class CmsMediaProviderTest extends TestCase
{
    private InMemoryCmsMediaLibrary $library;

    #[\Override]
    protected function setUp(): void
    {
        $this->library = new InMemoryCmsMediaLibrary();
        CmsMediaApi::add($this->library, 'yack/cms/hero.jpg', 'hero.jpg', '2026-11-01 10:00');
        CmsMediaApi::add($this->library, 'media/yack-cms/0123456789abcdef0123456789abcdef.jpg', 'Photo chantier.jpg', '2026-11-08 09:00');
    }

    public function testTheListGoesFromTheNewestToTheOldest(): void
    {
        $list = CmsMediaApi::provider($this->library)->provide(new GetCollection());

        $this->assertIsArray($list);
        $this->assertSame(['Photo chantier.jpg', 'hero.jpg'], array_map(static fn (CmsMediaView $view): string => $view->name, $list));
    }

    public function testTheListIsFilteredByTheQueryOfTheFiltersOrOfTheRequest(): void
    {
        $provider = CmsMediaApi::provider($this->library);

        $byFilters = $provider->provide(new GetCollection(), [], ['filters' => ['q' => 'CHANTIER']]);
        $byRequest = $provider->provide(new GetCollection(), [], ['request' => Request::create('/api/v2/admin/cms-media?q=hero')]);
        $ignored = $provider->provide(new GetCollection(), [], ['filters' => ['q' => ['x']]]);

        $this->assertIsArray($byFilters);
        $this->assertIsArray($byRequest);
        $this->assertIsArray($ignored);
        $this->assertSame(['Photo chantier.jpg'], array_map(static fn (CmsMediaView $view): string => $view->name, $byFilters));
        $this->assertSame(['hero.jpg'], array_map(static fn (CmsMediaView $view): string => $view->name, $byRequest));
        $this->assertCount(2, $ignored);
    }

    public function testAnImageIsFoundByItsIdWithItsThumbnail(): void
    {
        $view = CmsMediaApi::provider($this->library)->provide(new Get(), ['id' => '2']);

        $this->assertInstanceOf(CmsMediaView::class, $view);
        $this->assertSame('/media/cache/resolve/yack_cms_media_thumb/media/yack-cms/0123456789abcdef0123456789abcdef.jpg', $view->thumbnail);
        $this->assertSame('/media/yack-cms/0123456789abcdef0123456789abcdef.jpg', $view->url);
    }

    public function testAnUnknownOrMissingIdGivesNothing(): void
    {
        $provider = CmsMediaApi::provider($this->library);

        $this->assertNull($provider->provide(new Delete(), ['id' => 99]));
        $this->assertNull($provider->provide(new Get(), ['id' => 'abc']));
        $this->assertNull($provider->provide(new Post()));
    }
}
