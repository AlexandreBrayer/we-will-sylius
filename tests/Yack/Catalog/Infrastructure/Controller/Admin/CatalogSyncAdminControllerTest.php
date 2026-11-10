<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Controller\Admin;

use App\Entity\Catalog\SyncRun;
use App\Tests\Yack\Catalog\Application\FakeProductCatalog;
use App\Tests\Yack\Catalog\Application\FakeSearchIndexer;
use App\Tests\Yack\Catalog\Application\FakeSyncFailureNotifier;
use App\Tests\Yack\Catalog\Application\InMemorySyncRuns;
use App\Tests\Yack\Catalog\Application\RecordingLogger;
use App\Yack\Catalog\Application\ApplyProductFeed;
use App\Yack\Catalog\Application\ReplayRejectedRecords;
use App\Yack\Catalog\Application\SyncDashboard;
use App\Yack\Catalog\Application\SynchronizeCatalog;
use App\Yack\Catalog\Domain\ProductChange;
use App\Yack\Catalog\Domain\ProductSourceHealth;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SourceHealth;
use App\Yack\Catalog\Domain\SyncContext;
use App\Yack\Catalog\Domain\SyncOutcome;
use App\Yack\Catalog\Domain\SyncReport;
use App\Yack\Catalog\Domain\SyncSource;
use App\Yack\Catalog\Domain\SyncTrigger;
use App\Yack\Catalog\Infrastructure\Controller\Admin\CatalogSyncAdminController;
use App\Yack\Catalog\Infrastructure\Source\FeedRecordMapper;
use App\Yack\Catalog\Infrastructure\Source\SimulatedSourceOutage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Actions on the real use cases over fakes of the ports, Twig on minimal templates (AdminServices).
 *
 * @internal
 */
#[CoversClass(CatalogSyncAdminController::class)]
final class CatalogSyncAdminControllerTest extends TestCase
{
    private const TEMPLATES = [
        'admin/yack/catalog_sync/index.html.twig' => '{{ runs.total }} runs, page {{ runs.page }}, {{ filter.simulationsOnly ? "simulations" : "toutes" }}, {{ chart.days|length }} jours, {{ feed }}, dernière {{ latest ? latest.id : "aucune" }}',
        'admin/yack/catalog_sync/show.html.twig' => 'run {{ run.id }}, {{ outcome ? outcome.value : "tout" }}, {{ lines.total }} lignes, {{ replayable|length }} à rejouer, {{ editable|length }} colonnes, {{ replays|length }} rejeux, {{ replay_of ? "rejeu de " ~ replay_of.id : "original" }}',
    ];

    private InMemorySyncRuns $runs;

    private SimulatedSourceOutage $outage;

    private AdminServices $services;

    private CatalogSyncAdminController $controller;

    private ?SyncReport $report = null;

    /** @var list<object> */
    private array $dispatched = [];

    #[\Override]
    protected function setUp(): void
    {
        $this->runs = new InMemorySyncRuns();
        $this->outage = new SimulatedSourceOutage(new ArrayAdapter());
        $clock = new MockClock('2026-11-10 12:00:00');
        $apply = new ApplyProductFeed(new FakeProductCatalog(), $this->runs, new FakeSearchIndexer(), new FakeSyncFailureNotifier(), new RecordingLogger(), $clock);
        $health = $this->createStub(ProductSourceHealth::class);
        $health->method('check')->willReturn(new SourceHealth(true, 'Flux joignable.'));
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $message): Envelope {
            $this->dispatched[] = $message;

            return null === $this->report ? new Envelope($message) : (new Envelope($message))->with(new HandledStamp($this->report, 'handler'));
        });
        $product = $this->createStub(ProductInterface::class);
        $product->method('getId')->willReturn(42);
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?ProductInterface => 'SRK25ZS-W' === $criteria['code'] ? $product : null);

        $this->services = new AdminServices($this->createStub(RouterInterface::class), $this->createStub(CsrfTokenManagerInterface::class), self::TEMPLATES);
        $this->controller = new CatalogSyncAdminController(
            new SyncDashboard($this->runs, $health, $clock),
            $this->runs,
            new ReplayRejectedRecords($this->runs, new FeedRecordMapper(), $apply),
            $bus,
            $this->outage,
            $products,
            'data/yack/catalog.json',
        );
        $this->controller->setContainer($this->services->container);
    }

    public function testTheDashboardListsTheJournalWithTheFilterOfTheQuery(): void
    {
        $run = $this->completed(new SyncReport('catalog.json'), new SyncContext());
        $this->completed(new SyncReport('catalog.json', dryRun: true), new SyncContext(dryRun: true));

        $response = $this->controller->index($this->services->request(Request::create('/', 'GET', ['source' => 'simulation', 'page' => '1'])));

        $this->assertSame(sprintf('1 runs, page 1, simulations, 30 jours, data/yack/catalog.json, dernière %d', $run->getId()), $response->getContent());
    }

    public function testTheDetailOfARunWithItsLinesItsRejectionsToReplayAndItsReplays(): void
    {
        $report = new SyncReport('catalog.json');
        $report->record(new ProductChange('YK-1', 'Produit 1', SyncOutcome::Created));
        $report->fail(new RejectedRecord('YK-2', 'Prix négatif.', self::record('YK-2')));
        $run = $this->completed($report, new SyncContext());
        $replay = $this->completed(new SyncReport('Rejets'), new SyncContext(SyncSource::Replay, replayOf: $run->getId()));

        $show = $this->controller->show((int) $run->getId(), $this->services->request(Request::create('/', 'GET', ['resultat' => 'failed'])));
        $showReplay = $this->controller->show((int) $replay->getId(), $this->services->request(Request::create('/')));

        $this->assertSame('run 1, failed, 1 lignes, 1 à rejouer, 6 colonnes, 1 rejeux, original', $show->getContent());
        $this->assertSame('run 2, tout, 0 lignes, 0 à rejouer, 6 colonnes, 0 rejeux, rejeu de 1', $showReplay->getContent());
    }

    public function testAnUnknownRunIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Synchronisation run #99 not found.');

        $this->controller->show(99, $this->services->request(Request::create('/')));
    }

    /** @param array<string, list<string>> $flashes */
    #[DataProvider('provideRunReports')]
    public function testARunFromTheBackOfficeEndsOnItsDetailWithItsSummary(string $mode, SyncReport $report, array $flashes): void
    {
        $report->recordedAs(7);
        $this->report = $report;

        $response = $this->controller->synchronize($mode, $this->post());

        $this->assertRedirect('/app_yack_admin_catalog_sync_show?id=7', $response);
        $this->assertSame($flashes, $this->services->flashes());
        $message = $this->dispatched[0] ?? null;
        $this->assertInstanceOf(SynchronizeCatalog::class, $message);
        $this->assertSame(['data/yack/catalog.json', 'simulation' === $mode, SyncTrigger::BackOffice, 'admin'], [$message->feed, $message->dryRun, $message->trigger, $message->operator]);
    }

    /** @return \Iterator<string, array{string, SyncReport, array<string, list<string>>}> */
    public static function provideRunReports(): \Iterator
    {
        $simulation = new SyncReport('catalog.json', dryRun: true);
        $simulation->record(new ProductChange('YK-1', 'Produit 1', SyncOutcome::Updated));
        $rejected = new SyncReport('catalog.json');
        $rejected->fail(new RejectedRecord('YK-2', 'Prix absent.'));
        $unavailable = new SyncReport('catalog.json');
        $unavailable->sourceFailed('Flux injoignable.');

        yield 'simulation' => ['simulation', $simulation, ['success' => ['Simulation n° 7 terminée (Succès) : 0 créé, 1 mis à jour, 0 désactivé, 0 inchangé, 0 rejeté. Rien n\'a été écrit dans le catalogue.']]];
        yield 'rejets' => ['lancer', $rejected, ['warning' => ['Synchronisation n° 7 terminée (Partiel) : 0 créé, 0 mis à jour, 0 désactivé, 0 inchangé, 1 rejeté.']]];
        yield 'source indisponible' => ['lancer', $unavailable, ['error' => ['Synchronisation impossible : Flux injoignable. Le catalogue peut être importé à la main (fichier CSV ou XLSX).']]];
    }

    public function testARunWithoutRecordedReportIsAnError(): void
    {
        $this->report = new SyncReport('catalog.json');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The synchronisation returned no recorded report.');

        $this->controller->synchronize('lancer', $this->post());
    }

    public function testAnActionWithoutAValidCsrfTokenIsDenied(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Invalid CSRF token.');

        $this->controller->synchronize('lancer', $this->services->request(Request::create('/', 'POST', ['_token' => 'forgé'])));
    }

    public function testTheCorrectedRejectionsAreReplayedInANewRun(): void
    {
        $report = new SyncReport('catalog.json');
        $report->fail(new RejectedRecord('YK-2', 'Prix négatif.', self::record('YK-2')));
        $run = $this->completed($report, new SyncContext());
        $line = $this->runs->rejections($run)[0];

        $response = $this->controller->replay((int) $run->getId(), $this->post(['corrections' => [(int) $line->getId() => ['prix_ht' => '649,00']]]));

        $this->assertRedirect('/app_yack_admin_catalog_sync_show?id=2', $response);
        $this->assertSame(['success' => ['Synchronisation n° 2 terminée (Succès) : 1 créé, 0 mis à jour, 0 désactivé, 0 inchangé, 0 rejeté.']], $this->services->flashes());
        $this->assertSame([SyncSource::Replay, 'admin'], [$this->runs->get(2)?->getSource(), $this->runs->get(2)?->getOperator()]);
    }

    public function testAReplayStillRejectedEndsWithAWarning(): void
    {
        $report = new SyncReport('catalog.json');
        $report->fail(new RejectedRecord('YK-2', 'Prix négatif.', self::record('YK-2')));
        $run = $this->completed($report, new SyncContext());

        $this->controller->replay((int) $run->getId(), $this->post());

        $this->assertSame(['warning'], array_keys($this->services->flashes()));
    }

    public function testARunWithNothingToReplayStaysOnItsDetail(): void
    {
        $run = $this->completed(new SyncReport('catalog.json'), new SyncContext());

        $response = $this->controller->replay((int) $run->getId(), $this->post());

        $this->assertRedirect('/app_yack_admin_catalog_sync_show?id=1', $response);
        $this->assertSame(['warning' => ['Cette synchronisation n\'a aucun enregistrement rejeté à rejouer.']], $this->services->flashes());
    }

    public function testAnOutageOfTheApiIsSimulatedThenEnded(): void
    {
        $down = $this->controller->outage('panne', $this->post());
        $isDown = $this->outage->isOn();
        $up = $this->controller->outage('retablie', $this->post());

        $this->assertRedirect('/app_yack_admin_catalog_sync_index', $down);
        $this->assertRedirect('/app_yack_admin_catalog_sync_index', $up);
        $this->assertSame([true, false], [$isDown, $this->outage->isOn()]);
        $this->assertSame([
            'warning' => ['Panne de l\'API Yack simulée : les prochaines synchronisations échoueront. L\'import manuel (CSV ou XLSX) reste disponible.'],
            'success' => ['API Yack rétablie : les synchronisations lisent de nouveau le flux.'],
        ], $this->services->flashes());
    }

    public function testAProductOfTheJournalOpensItsSheetInTheBackOffice(): void
    {
        $this->assertRedirect('/sylius_admin_product_show?id=42', $this->controller->product('SRK25ZS-W'));

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Product "YK-ABSENT" not found.');

        $this->controller->product('YK-ABSENT');
    }

    public function testTheChartHasOneColumnSeriesPerStatusAndTheLineOfTheProductsWritten(): void
    {
        $chart = CatalogSyncAdminController::chart([
            ['day' => '09/11', 'success' => 1, 'partial' => 0, 'failure' => 1, 'written' => 4],
            ['day' => '10/11', 'success' => 0, 'partial' => 2, 'failure' => 0, 'written' => 7],
        ]);

        $this->assertSame([
            'days' => ['09/11', '10/11'],
            'series' => [
                ['name' => 'Succès', 'type' => 'column', 'data' => [1, 0]],
                ['name' => 'Partiel', 'type' => 'column', 'data' => [0, 2]],
                ['name' => 'Échec', 'type' => 'column', 'data' => [1, 0]],
                ['name' => 'Produits écrits', 'type' => 'line', 'data' => [4, 7]],
            ],
        ], $chart);
    }

    public function testTheSummaryOfARun(): void
    {
        $report = new SyncReport('tarifs.csv');
        $report->record(new ProductChange('YK-1', 'Produit 1', SyncOutcome::Created));
        $report->fail(new RejectedRecord('YK-2', 'Prix absent.'));
        $report->recordedAs(8);

        $this->assertSame('Synchronisation n° 8 terminée (Partiel) : 1 créé, 0 mis à jour, 0 désactivé, 0 inchangé, 1 rejeté.', CatalogSyncAdminController::summary($report));
    }

    public function testTheCorrectionsKeepOnlyTextsByLineAndColumn(): void
    {
        $corrections = CatalogSyncAdminController::corrections([
            12 => ['prix_ht' => '649,00', 'stock' => ['3'], 4 => 'x', 'nom' => str_repeat('é', 300)],
            'abc' => ['prix_ht' => '1'],
            13 => 'pas un tableau',
            14 => [],
        ]);

        $this->assertSame([12 => ['prix_ht' => '649,00', 'nom' => str_repeat('é', 255)]], $corrections);
    }

    private function completed(SyncReport $report, SyncContext $context): SyncRun
    {
        $run = SyncRun::completed($report, $context, new \DateTimeImmutable('2026-11-10 06:00'), new \DateTimeImmutable('2026-11-10 06:00'));
        $this->runs->add($run);

        return $run;
    }

    /** @param array<string, mixed> $fields */
    private function post(array $fields = []): Request
    {
        return $this->services->request(Request::create('/', 'POST', ['_token' => AdminServices::VALID_TOKEN] + $fields));
    }

    private function assertRedirect(string $url, Response $response): void
    {
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame($url, $response->getTargetUrl());
    }

    /** @return array<string, mixed> */
    private static function record(string $sku): array
    {
        return ['sku' => $sku, 'label' => 'Pompe ' . $sku, 'type' => 'finished', 'status' => 'active', 'category' => 'pac_air_eau', 'price' => ['amount' => '-5', 'currency' => 'EUR'], 'stock' => 3];
    }
}
