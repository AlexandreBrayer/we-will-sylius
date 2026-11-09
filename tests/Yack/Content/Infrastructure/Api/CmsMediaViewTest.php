<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use App\Tests\Yack\Content\Application\CmsMediaImages;
use App\Tests\Yack\Content\Application\InMemoryCmsMediaLibrary;
use App\Yack\Content\Infrastructure\Api\CmsMediaView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 */
#[CoversClass(CmsMediaView::class)]
final class CmsMediaViewTest extends TestCase
{
    public function testTheReadGroupGivesTheContractOfTheMediaLibrary(): void
    {
        $library = new InMemoryCmsMediaLibrary();
        CmsMediaApi::add($library, 'yack/cms/marque-york.png', 'marque-york.png', '2026-11-08T10:00:00+00:00', CmsMediaImages::png(30, 20));
        $view = CmsMediaApi::provider($library)->view($library->media[1]);

        $serializer = new Serializer([new ObjectNormalizer(new ClassMetadataFactory(new AttributeLoader()), propertyTypeExtractor: new ReflectionExtractor())]);

        $this->assertSame([
            'id' => 1,
            'path' => 'yack/cms/marque-york.png',
            'url' => '/yack/cms/marque-york.png',
            'thumbnail' => '/media/cache/resolve/yack_cms_media_thumb/yack/cms/marque-york.png',
            'name' => 'marque-york.png',
            'mediaType' => 'image/png',
            'width' => 30,
            'height' => 20,
            'size' => \strlen(CmsMediaImages::png(30, 20)),
            'alt' => 'Texte',
            'author' => null,
            'createdAt' => '2026-11-08T10:00:00+00:00',
            'uploaded' => false,
        ], $serializer->normalize($view, null, ['groups' => ['cms_media:read']]));
        $this->assertSame(['alt' => 'Texte'], $serializer->normalize($view, null, ['groups' => ['cms_media:write']]));
    }
}
