<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Controller\Admin;

use App\Yack\Catalog\Application\Import\ImportCatalogFile;
use App\Yack\Catalog\Domain\Import\ImportColumn;
use App\Yack\Catalog\Domain\Import\ImportFile;
use App\Yack\Catalog\Domain\Import\ImportFileRefused;
use App\Yack\Catalog\Domain\Import\ImportFormat;
use App\Yack\Catalog\Domain\ProductSourceHealth;
use App\Yack\Catalog\Domain\SyncOutcome;
use App\Yack\Catalog\Infrastructure\Import\ImportTemplates;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Back-office: manual import of the catalog (CSV or XLSX) when the Yack API does not answer.
 * 1. upload, 2. preview (columns, rejected lines, changes), 3. confirmation, recorded as a run.
 * Secured by the Sylius admin firewall (path under /%sylius_admin.path_name%).
 */
#[Route('/%sylius_admin.path_name%/yack/synchronisation')]
final class CatalogImportAdminController extends AbstractController
{
    public function __construct(
        private readonly ImportCatalogFile $import,
        private readonly ProductSourceHealth $health,
        private readonly ImportTemplates $templates,
    ) {
    }

    #[Route('/import', name: 'app_yack_admin_catalog_sync_import', methods: ['GET', 'POST'])]
    public function upload(Request $request): Response
    {
        $error = null;
        if ($request->isMethod('POST')) {
            $this->checkCsrf('yack_catalog_import', $request);
            $file = $request->files->get('fichier');

            try {
                $stored = $this->store($file);

                return $this->redirectToRoute('app_yack_admin_catalog_sync_import_preview', ['token' => $stored->token]);
            } catch (ImportFileRefused $exception) {
                $error = $exception->getMessage();
            }
        }

        return $this->render('admin/yack/catalog_sync/import.html.twig', [
            'error' => $error,
            'columns' => ImportColumn::cases(),
            'max_megabytes' => intdiv(ImportFile::MAX_BYTES, 1024 * 1024),
            'max_rows' => ImportFile::MAX_ROWS,
            'health' => $this->health->check(),
        ], new Response(status: null === $error ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    #[Route('/import/{token<[a-f0-9]{32}>}', name: 'app_yack_admin_catalog_sync_import_preview', methods: ['GET'])]
    public function preview(string $token): Response
    {
        $file = $this->storedFile($token);

        try {
            $preview = $this->import->preview($file);
        } catch (ImportFileRefused $exception) {
            $this->import->cancel($file);
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_yack_admin_catalog_sync_import');
        }

        return $this->render('admin/yack/catalog_sync/import_preview.html.twig', [
            'preview' => $preview,
            'missing_message' => [] === $preview->mapping->missing() ? null : ImportCatalogFile::missingMessage($preview->mapping->missing()),
            'outcomes' => SyncOutcome::cases(),
        ]);
    }

    #[Route('/import/{token<[a-f0-9]{32}>}/confirmer', name: 'app_yack_admin_catalog_sync_import_confirm', methods: ['POST'])]
    public function confirm(string $token, Request $request): Response
    {
        $this->checkCsrf('yack_catalog_import_' . $token, $request);
        $file = $this->storedFile($token);

        try {
            $report = $this->import->confirm($file, $this->getUser()?->getUserIdentifier());
        } catch (ImportFileRefused $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_yack_admin_catalog_sync_import_preview', ['token' => $token]);
        }
        $this->addFlash($report->hasFailures() ? 'warning' : 'success', CatalogSyncAdminController::summary($report));

        return $this->redirectToRoute('app_yack_admin_catalog_sync_show', ['id' => $report->runId()]);
    }

    #[Route('/import/{token<[a-f0-9]{32}>}/annuler', name: 'app_yack_admin_catalog_sync_import_cancel', methods: ['POST'])]
    public function cancel(string $token, Request $request): Response
    {
        $this->checkCsrf('yack_catalog_import_' . $token, $request);
        $this->import->cancel($this->storedFile($token));
        $this->addFlash('info', 'Import annulé : le fichier a été supprimé, rien n\'a été écrit.');

        return $this->redirectToRoute('app_yack_admin_catalog_sync_import');
    }

    #[Route('/modele.{format<csv|xlsx>}', name: 'app_yack_admin_catalog_sync_template', methods: ['GET'])]
    public function template(string $format): Response
    {
        $format = ImportFormat::from($format);

        return new Response($this->templates->content($format), Response::HTTP_OK, [
            'Content-Type' => $format->contentType(),
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, ImportTemplates::fileName($format)),
        ]);
    }

    /** @throws ImportFileRefused */
    private function store(mixed $file): ImportFile
    {
        if (!$file instanceof UploadedFile) {
            throw new ImportFileRefused('Choisissez un fichier CSV ou XLSX.');
        }
        if (!$file->isValid()) {
            throw new ImportFileRefused(\UPLOAD_ERR_INI_SIZE === $file->getError() || \UPLOAD_ERR_FORM_SIZE === $file->getError()
                ? sprintf('Fichier trop volumineux (%d Mo maximum).', intdiv(ImportFile::MAX_BYTES, 1024 * 1024))
                : 'Le fichier n\'a pas pu être reçu, réessayez.');
        }

        return $this->import->store($file->getPathname(), $file->getClientOriginalName());
    }

    private function storedFile(string $token): ImportFile
    {
        return $this->import->file($token) ?? throw new NotFoundHttpException('Import file not found: already imported, cancelled or expired.');
    }

    private function checkCsrf(string $id, Request $request): void
    {
        if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
