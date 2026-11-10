<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Controller\Admin;

use App\Tests\Yack\Catalog\Application\FakeProductCatalog;
use App\Tests\Yack\Catalog\Application\FakeSearchIndexer;
use App\Tests\Yack\Catalog\Application\FakeSyncFailureNotifier;
use App\Tests\Yack\Catalog\Application\Import\InMemoryImportFiles;
use App\Tests\Yack\Catalog\Application\InMemorySyncRuns;
use App\Tests\Yack\Catalog\Application\RecordingLogger;
use App\Yack\Catalog\Application\ApplyProductFeed;
use App\Yack\Catalog\Application\Import\ImportCatalogFile;
use App\Yack\Catalog\Domain\Import\ImportFile;
use App\Yack\Catalog\Domain\Import\ImportFileRefused;
use App\Yack\Catalog\Domain\Import\SpreadsheetReader;
use App\Yack\Catalog\Domain\Import\SpreadsheetRows;
use App\Yack\Catalog\Domain\ProductSourceHealth;
use App\Yack\Catalog\Domain\SourceHealth;
use App\Yack\Catalog\Domain\SyncSource;
use App\Yack\Catalog\Infrastructure\Controller\Admin\CatalogImportAdminController;
use App\Yack\Catalog\Infrastructure\Import\ImportTemplates;
use App\Yack\Catalog\Infrastructure\Source\FeedRecordMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Actions on the real use case over fakes of the ports, Twig on minimal templates (AdminServices).
 *
 * @internal
 */
#[CoversClass(CatalogImportAdminController::class)]
final class CatalogImportAdminControllerTest extends TestCase
{
    private const TEMPLATES = [
        'admin/yack/catalog_sync/import.html.twig' => '{{ error ?? "sans erreur" }}, {{ columns|length }} colonnes, {{ max_megabytes }} Mo, {{ max_rows }} lignes, {{ health.message }}',
        'admin/yack/catalog_sync/import_preview.html.twig' => '{{ preview.file.originalName }}, {{ preview.lineCount }} lignes, {{ missing_message ?? "colonnes complètes" }}, {{ outcomes|length }} résultats',
    ];

    private const HEADERS = ['Code', 'Nom', 'Nature', 'Actif', 'Catégorie', 'Prix HT', 'Stock'];

    private InMemoryImportFiles $files;

    private InMemorySyncRuns $runs;

    private SpreadsheetRows|ImportFileRefused $rows;

    private AdminServices $services;

    private CatalogImportAdminController $controller;

    private string $upload;

    #[\Override]
    protected function setUp(): void
    {
        $this->files = new InMemoryImportFiles();
        $this->runs = new InMemorySyncRuns();
        $this->rows = new SpreadsheetRows(self::HEADERS, [2 => ['SRK25ZS-W', 'Console', 'Produit fini', 'oui', 'pac_air_air', '594,90', '12']]);
        $reader = $this->createStub(SpreadsheetReader::class);
        $reader->method('read')->willReturnCallback(fn (): SpreadsheetRows => $this->rows instanceof ImportFileRefused ? throw $this->rows : $this->rows);
        $health = $this->createStub(ProductSourceHealth::class);
        $health->method('check')->willReturn(new SourceHealth(false, 'API Yack injoignable.'));
        $apply = new ApplyProductFeed(new FakeProductCatalog(), $this->runs, new FakeSearchIndexer(), new FakeSyncFailureNotifier(), new RecordingLogger(), new MockClock('2026-11-10 12:00:00'));

        $this->services = new AdminServices($this->createStub(RouterInterface::class), $this->createStub(CsrfTokenManagerInterface::class), self::TEMPLATES);
        $this->controller = new CatalogImportAdminController(new ImportCatalogFile($this->files, $reader, new FeedRecordMapper(), $apply), $health, new ImportTemplates());
        $this->controller->setContainer($this->services->container);
        $this->upload = (string) tempnam(sys_get_temp_dir(), 'yack-upload');
        file_put_contents($this->upload, "Code\n");
    }

    #[\Override]
    protected function tearDown(): void
    {
        unlink($this->upload);
    }

    public function testTheUploadFormShowsTheContractOfTheColumnsAndTheHealthOfTheApi(): void
    {
        $response = $this->controller->upload($this->services->request(Request::create('/')));

        $this->assertSame([Response::HTTP_OK, 'sans erreur, 20 colonnes, 5 Mo, 5000 lignes, API Yack injoignable.'], [$response->getStatusCode(), $response->getContent()]);
    }

    public function testAnUploadedFileIsStoredThenPreviewed(): void
    {
        $response = $this->controller->upload($this->post(files: ['fichier' => new UploadedFile($this->upload, 'tarifs.csv', test: true)]));

        $file = array_values($this->files->files)[0] ?? null;
        $this->assertInstanceOf(ImportFile::class, $file);
        $this->assertSame([$this->upload, 'tarifs.csv'], [$file->path, $file->originalName]);
        $this->assertRedirect('/app_yack_admin_catalog_sync_import_preview?token=' . $file->token, $response);
    }

    #[DataProvider('provideRefusedUploads')]
    public function testARefusedUploadShowsTheFormAgainWithTheReason(?int $error, string $message): void
    {
        $files = null === $error ? [] : ['fichier' => new UploadedFile($this->upload, 'tarifs.csv', error: $error, test: true)];

        $response = $this->controller->upload($this->post(files: $files));

        $this->assertSame([Response::HTTP_UNPROCESSABLE_ENTITY, $message . ', 20 colonnes, 5 Mo, 5000 lignes, API Yack injoignable.'], [$response->getStatusCode(), $response->getContent()]);
        $this->assertSame([], $this->files->files);
    }

    /** @return \Iterator<string, array{int|null, string}> */
    public static function provideRefusedUploads(): \Iterator
    {
        yield 'aucun fichier' => [null, 'Choisissez un fichier CSV ou XLSX.'];
        yield 'limite du serveur' => [\UPLOAD_ERR_INI_SIZE, 'Fichier trop volumineux (5 Mo maximum).'];
        yield 'limite du formulaire' => [\UPLOAD_ERR_FORM_SIZE, 'Fichier trop volumineux (5 Mo maximum).'];
        yield 'envoi interrompu' => [\UPLOAD_ERR_PARTIAL, 'Le fichier n\'a pas pu être reçu, réessayez.'];
    }

    public function testAnUploadWithoutAValidCsrfTokenIsDenied(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Invalid CSRF token.');

        $this->controller->upload($this->services->request(Request::create('/', 'POST', ['_token' => 'forgé'])));
    }

    public function testThePreviewShowsTheLinesOfTheFile(): void
    {
        $file = $this->files->store($this->upload, 'tarifs.csv');

        $this->assertSame('tarifs.csv, 1 lignes, colonnes complètes, 5 résultats', $this->controller->preview($file->token)->getContent());
    }

    public function testThePreviewOfAFileWithoutTheRequiredColumnsSaysWhichAreMissing(): void
    {
        $file = $this->files->store($this->upload, 'tarifs.csv');
        $this->rows = new SpreadsheetRows(['Code', 'Nom'], []);

        $this->assertSame('tarifs.csv, 0 lignes, Colonnes obligatoires absentes : Nature, Actif, Catégorie, Prix HT, Stock. Téléchargez le modèle pour voir les en-têtes attendus., 5 résultats', $this->controller->preview($file->token)->getContent());
    }

    public function testAnUnreadableFileIsDeletedAndTheFormShownAgain(): void
    {
        $file = $this->files->store($this->upload, 'tarifs.csv');
        $this->rows = new ImportFileRefused('Le fichier ne contient aucune ligne.');

        $response = $this->controller->preview($file->token);

        $this->assertRedirect('/app_yack_admin_catalog_sync_import', $response);
        $this->assertSame([$file->token], $this->files->deleted);
        $this->assertSame(['error' => ['Le fichier ne contient aucune ligne.']], $this->services->flashes());
    }

    public function testAnUnknownImportIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Import file not found: already imported, cancelled or expired.');

        $this->controller->preview(str_repeat('0', 32));
    }

    public function testTheConfirmedImportIsARunOfTheJournal(): void
    {
        $file = $this->files->store($this->upload, 'tarifs.csv');

        $response = $this->controller->confirm($file->token, $this->post());

        $this->assertRedirect('/app_yack_admin_catalog_sync_show?id=1', $response);
        $this->assertSame(['success' => ['Synchronisation n° 1 terminée (Succès) : 1 créés, 0 mis à jour, 0 désactivés, 0 inchangés, 0 rejetés.']], $this->services->flashes());
        $this->assertSame([SyncSource::Csv, 'admin'], [$this->runs->get(1)?->getSource(), $this->runs->get(1)?->getOperator()]);
    }

    public function testAnImportWithRejectedLinesEndsWithAWarning(): void
    {
        $file = $this->files->store($this->upload, 'tarifs.csv');
        $this->rows = new SpreadsheetRows(self::HEADERS, [
            2 => ['SRK25ZS-W', 'Console', 'Produit fini', 'oui', 'pac_air_air', '594,90', '12'],
            3 => ['YK-2', 'Pompe', 'Produit fini', 'oui', 'pac_air_air', 'cher', '1'],
        ]);

        $this->controller->confirm($file->token, $this->post());

        $this->assertSame(['warning'], array_keys($this->services->flashes()));
    }

    public function testAFileWithoutTheRequiredColumnsGoesBackToItsPreview(): void
    {
        $file = $this->files->store($this->upload, 'tarifs.csv');
        $this->rows = new SpreadsheetRows(['Code', 'Nom'], []);

        $response = $this->controller->confirm($file->token, $this->post());

        $this->assertRedirect('/app_yack_admin_catalog_sync_import_preview?token=' . $file->token, $response);
        $this->assertSame(['error' => ['Colonnes obligatoires absentes : Nature, Actif, Catégorie, Prix HT, Stock. Téléchargez le modèle pour voir les en-têtes attendus.']], $this->services->flashes());
        $this->assertSame([], $this->runs->runs);
    }

    public function testACancelledImportDeletesTheFile(): void
    {
        $file = $this->files->store($this->upload, 'tarifs.csv');

        $response = $this->controller->cancel($file->token, $this->post());

        $this->assertRedirect('/app_yack_admin_catalog_sync_import', $response);
        $this->assertSame([$file->token], $this->files->deleted);
        $this->assertSame(['info' => ['Import annulé : le fichier a été supprimé, rien n\'a été écrit.']], $this->services->flashes());
    }

    #[DataProvider('provideTemplates')]
    public function testTheTemplateIsDownloadedAsAnAttachment(string $format, string $contentType): void
    {
        $response = $this->controller->template($format);

        $this->assertSame([$contentType, 'attachment; filename=modele-import-catalogue.' . $format], [$response->headers->get('Content-Type'), $response->headers->get('Content-Disposition')]);
        $this->assertNotSame('', $response->getContent());
    }

    /** @return \Iterator<string, array{string, string}> */
    public static function provideTemplates(): \Iterator
    {
        yield 'csv' => ['csv', 'text/csv; charset=UTF-8'];
        yield 'xlsx' => ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    }

    /**
     * @param array<string, UploadedFile> $files
     */
    private function post(array $files = []): Request
    {
        return $this->services->request(Request::create('/', 'POST', ['_token' => AdminServices::VALID_TOKEN], files: $files));
    }

    private function assertRedirect(string $url, Response $response): void
    {
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame($url, $response->getTargetUrl());
    }
}
