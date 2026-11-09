<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Yack\Content\Application\DeleteCmsMedia;
use App\Yack\Content\Domain\CmsMediaInUse;
use App\Yack\Content\Domain\CmsMediaLibrary;
use App\Yack\Content\Domain\CmsMediaUsage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * DELETE /admin/cms-media/{id}: 204 when no page, template or section shows the image (DeleteCmsMedia),
 * else 409 application/problem+json with `detail` (French, the contents named) and `usages`
 * ([{kind: page|template|section, id, title}], id = slug of a page, id of a template or of a section)
 * for the builder.
 *
 * @implements ProcessorInterface<CmsMediaView, Response|null>
 */
final readonly class CmsMediaDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private CmsMediaLibrary $library,
        private DeleteCmsMedia $delete,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Response
    {
        $media = $this->library->find($data->id) ?? throw new NotFoundHttpException('Image introuvable.');

        try {
            ($this->delete)($media);
        } catch (CmsMediaInUse $inUse) {
            return new JsonResponse([
                'title' => 'Image utilisée',
                'status' => Response::HTTP_CONFLICT,
                'detail' => $inUse->getMessage(),
                'usages' => array_map(static fn (CmsMediaUsage $usage): array => ['kind' => $usage->kind, 'id' => $usage->id, 'title' => $usage->title], $inUse->usages),
            ], Response::HTTP_CONFLICT, ['Content-Type' => 'application/problem+json']);
        }

        return null;
    }
}
