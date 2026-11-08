<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Yack\AfterSales\Domain\NameplatePhoto;
use App\Yack\AfterSales\Domain\NameplatePhotoRefused;
use App\Yack\Assistance\Application\ReadPlate;
use App\Yack\Common\Domain\AiUnavailable;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Lit la photo envoyée en mémoire (erreurs d'envoi, 20 Mo), supprime le fichier temporaire de PHP (la photo n'est
 * jamais conservée, docs/adr/0005-fichiers-televerses.md), puis lance `ReadPlate` : lecture seule, aucune recherche.
 * Photo refusée : 422 sur `photo` ; lecture indisponible : 503 ; module coupé : 404.
 *
 * @implements ProcessorInterface<null, PlateReadingView>
 */
final readonly class PlateReadingProcessor implements ProcessorInterface
{
    public const OPERATION = 'app_yack_api_shop_assistance_plate_reading_post';

    public const FIELD = 'photo';

    public const REQUIRED_MESSAGE = 'Photo obligatoire.';

    public function __construct(
        private AssistanceSwitch $switch,
        private ReadPlate $readPlate,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PlateReadingView
    {
        $this->switch->assertEnabled();

        $request = $context['request'] ?? null;
        $file = $request instanceof Request ? $request->files->get(self::FIELD) : null;
        if (!$file instanceof UploadedFile) {
            throw self::invalid(self::REQUIRED_MESSAGE);
        }

        try {
            $bytes = self::bytes($file);
        } finally {
            @unlink($file->getPathname());
        }

        try {
            return PlateReadingView::of(($this->readPlate)($bytes));
        } catch (NameplatePhotoRefused $refused) {
            throw self::invalid($refused->getMessage());
        } catch (AiUnavailable) {
            throw new ServiceUnavailableHttpException(null, ReadPlate::UNAVAILABLE_MESSAGE);
        }
    }

    private static function bytes(UploadedFile $file): string
    {
        if (\in_array($file->getError(), [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true)) {
            throw self::invalid(NameplatePhotoRefused::tooLarge()->getMessage());
        }
        if (!$file->isValid()) {
            throw self::invalid(self::REQUIRED_MESSAGE);
        }
        if ($file->getSize() > NameplatePhoto::MAX_BYTES) {
            throw self::invalid(NameplatePhotoRefused::tooLarge()->getMessage());
        }

        return (string) file_get_contents($file->getPathname());
    }

    private static function invalid(string $message): ValidationException
    {
        return new ValidationException(new ConstraintViolationList([
            new ConstraintViolation($message, null, [], null, self::FIELD, null),
        ]));
    }
}
