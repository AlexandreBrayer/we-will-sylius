<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Yack\AfterSales\Application\ApplianceIdentification;
use App\Yack\AfterSales\Application\IdentificationStatus;
use App\Yack\AfterSales\Application\IdentifyApplianceFromPhoto;
use App\Yack\AfterSales\Domain\NameplatePhoto;
use App\Yack\AfterSales\Domain\NameplatePhotoRefused;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Reads the uploaded photo in memory, checks it (type read on the content with finfo, size), runs
 * IdentifyApplianceFromPhoto, then deletes PHP's temporary file: the photo is never stored
 * (docs/adr/0005-fichiers-televerses.md).
 *
 * @implements ProcessorInterface<null, NameplateReadingView>
 */
final readonly class NameplateReadingProcessor implements ProcessorInterface
{
    public const FIELD = 'photo';

    public function __construct(private IdentifyApplianceFromPhoto $identify)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NameplateReadingView
    {
        $request = $context['request'] ?? null;
        $file = $request instanceof Request ? $request->files->get(self::FIELD) : null;
        if (!$file instanceof UploadedFile) {
            throw self::invalid('Photo obligatoire.');
        }

        try {
            $photo = $this->photo($file);
        } finally {
            @unlink($file->getPathname());
        }

        $identification = ($this->identify)($photo);
        if (IdentificationStatus::Unavailable === $identification->status) {
            throw new ServiceUnavailableHttpException(null, ApplianceIdentification::UNAVAILABLE_MESSAGE);
        }

        return NameplateReadingView::of($identification);
    }

    private function photo(UploadedFile $file): NameplatePhoto
    {
        if (\in_array($file->getError(), [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true)) {
            throw self::invalid(NameplatePhotoRefused::tooLarge()->getMessage());
        }
        if (!$file->isValid()) {
            throw self::invalid('Photo obligatoire.');
        }
        if ($file->getSize() > NameplatePhoto::MAX_BYTES) {
            throw self::invalid(NameplatePhotoRefused::tooLarge()->getMessage());
        }

        $bytes = (string) file_get_contents($file->getPathname());
        $mediaType = '' === $bytes ? '' : (string) (new \finfo(\FILEINFO_MIME_TYPE))->buffer($bytes);

        try {
            return NameplatePhoto::of($bytes, $mediaType);
        } catch (NameplatePhotoRefused $refused) {
            throw self::invalid($refused->getMessage());
        }
    }

    private static function invalid(string $message): ValidationException
    {
        return new ValidationException(new ConstraintViolationList([
            new ConstraintViolation($message, null, [], null, self::FIELD, null),
        ]));
    }
}
