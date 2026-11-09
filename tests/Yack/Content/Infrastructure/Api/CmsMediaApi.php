<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use App\Entity\Content\CmsMedia;
use App\Tests\Yack\Content\Application\CmsMediaImages;
use App\Tests\Yack\Content\Application\InMemoryCmsMediaLibrary;
use App\Yack\Content\Domain\CmsMediaFile;
use App\Yack\Content\Infrastructure\Api\CmsMediaProvider;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/** Shared set-up of the media library API tests: provider with the real LiipImagine route, images added at fixed dates. */
final class CmsMediaApi
{
    public static function provider(InMemoryCmsMediaLibrary $library): CmsMediaProvider
    {
        $routes = new RouteCollection();
        $routes->add('liip_imagine_filter', new Route('/media/cache/resolve/{filter}/{path}', requirements: ['filter' => '[A-z0-9_-]*', 'path' => '.+']));

        return new CmsMediaProvider($library, new UrlGenerator($routes, new RequestContext()));
    }

    public static function add(InMemoryCmsMediaLibrary $library, string $path, string $name, string $createdAt, string $bytes = ''): CmsMedia
    {
        $media = new CmsMedia($path, $name, CmsMediaFile::read('' === $bytes ? CmsMediaImages::jpeg(64, 48) : $bytes), 'Texte', null, new \DateTimeImmutable($createdAt));
        $library->add($media);

        return $media;
    }
}
