<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\User\AdminUser;
use App\Yack\Content\Application\UploadCmsMedia;
use App\Yack\Content\Domain\CmsMediaFile;
use App\Yack\Content\Domain\CmsMediaRefused;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * POST /admin/cms-media (multipart, field `file`, optional `alt`): reads the uploaded file, adds it to the
 * library (UploadCmsMedia: type read on the content, random name), then deletes PHP's temporary file.
 * Only the bytes and the name sent are used: no path of the request reaches the disk.
 *
 * @implements ProcessorInterface<null, CmsMediaView>
 */
final readonly class CmsMediaUploadProcessor implements ProcessorInterface
{
    public const FIELD = 'file';

    public const ALT_FIELD = 'alt';

    public function __construct(
        private UploadCmsMedia $upload,
        private CmsMediaProvider $provider,
        private TokenStorageInterface $tokens,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CmsMediaView
    {
        $request = $context['request'] ?? null;
        $file = $request instanceof Request ? $request->files->get(self::FIELD) : null;
        $alt = $request instanceof Request ? $request->request->all()[self::ALT_FIELD] ?? '' : '';
        if (!is_string($alt)) {
            $alt = '';
        }

        if (!$file instanceof UploadedFile) {
            throw self::invalid(self::FIELD, CmsMediaRefused::missing());
        }

        try {
            try {
                $alt = CmsMediaFile::cleanAlt($alt);
            } catch (CmsMediaRefused $refused) {
                throw self::invalid(self::ALT_FIELD, $refused);
            }

            try {
                $media = ($this->upload)(self::bytes($file), $file->getClientOriginalName(), $alt, $this->authorName());
            } catch (CmsMediaRefused $refused) {
                throw self::invalid(self::FIELD, $refused);
            }
        } finally {
            @unlink($file->getPathname());
        }

        return $this->provider->view($media);
    }

    /**
     * @throws CmsMediaRefused
     */
    private static function bytes(UploadedFile $file): string
    {
        if (\in_array($file->getError(), [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true)) {
            throw CmsMediaRefused::tooLarge();
        }
        if (\UPLOAD_ERR_NO_FILE === $file->getError()) {
            throw CmsMediaRefused::missing();
        }
        if (!$file->isValid()) {
            throw CmsMediaRefused::interrupted();
        }
        if ($file->getSize() > CmsMediaFile::MAX_BYTES) {
            throw CmsMediaRefused::tooLarge();
        }

        return (string) file_get_contents($file->getPathname());
    }

    private function authorName(): ?string
    {
        $user = $this->tokens->getToken()?->getUser();
        if (!$user instanceof AdminUser) {
            return null;
        }

        $name = trim(($user->getFirstName() ?? '') . ' ' . ($user->getLastName() ?? ''));

        return '' !== $name ? $name : $user->getEmail();
    }

    private static function invalid(string $field, CmsMediaRefused $refused): ValidationException
    {
        return new ValidationException(new ConstraintViolationList([
            new ConstraintViolation($refused->getMessage(), null, [], null, $field, null),
        ]));
    }
}
