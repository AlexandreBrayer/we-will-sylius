<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Yack\Content\Domain\CmsMediaLibrary;
use App\Yack\Content\Domain\CmsMediaRefused;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * PUT /admin/cms-media/{id} {"alt": "..."}: alternative text proposed by default (one line, 255 characters
 * at most). Nothing else of an image changes.
 *
 * @implements ProcessorInterface<CmsMediaView, CmsMediaView>
 */
final readonly class CmsMediaAltProcessor implements ProcessorInterface
{
    public function __construct(
        private CmsMediaLibrary $library,
        private CmsMediaProvider $provider,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CmsMediaView
    {
        $media = $this->library->find($data->id) ?? throw new NotFoundHttpException('Image introuvable.');

        try {
            $media->describe($data->alt);
        } catch (CmsMediaRefused $refused) {
            throw new ValidationException(new ConstraintViolationList([
                new ConstraintViolation($refused->getMessage(), null, [], $data, 'alt', $data->alt),
            ]));
        }
        $this->library->save($media);

        return $this->provider->view($media);
    }
}
