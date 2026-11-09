<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Content\CmsMedia;
use App\Yack\Content\Domain\CmsMediaLibrary;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Images of the media library (CmsMediaView), the list filtered by `?q=` on the name.
 *
 * @implements ProviderInterface<CmsMediaView>
 */
final readonly class CmsMediaProvider implements ProviderInterface
{
    public const THUMBNAIL_FILTER = 'yack_cms_media_thumb';

    public function __construct(
        private CmsMediaLibrary $library,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @return CmsMediaView|list<CmsMediaView>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CmsMediaView|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            return array_map($this->view(...), $this->library->search(self::query($context)));
        }

        $id = $uriVariables['id'] ?? null;
        $media = is_numeric($id) ? $this->library->find((int) $id) : null;

        return null === $media ? null : $this->view($media);
    }

    public function view(CmsMedia $media): CmsMediaView
    {
        return CmsMediaView::of($media, $this->urls->generate(
            'liip_imagine_filter',
            ['filter' => self::THUMBNAIL_FILTER, 'path' => $media->getPath()],
            UrlGeneratorInterface::ABSOLUTE_PATH,
        ));
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function query(array $context): ?string
    {
        $filters = $context['filters'] ?? null;
        $query = is_array($filters) ? ($filters['q'] ?? null) : null;
        if (null === $query && ($context['request'] ?? null) instanceof Request) {
            $query = $context['request']->query->all()['q'] ?? null;
        }

        return is_string($query) ? $query : null;
    }
}
