<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Controller\Admin;

use App\Entity\Catalog\SyncRun;
use App\Yack\Catalog\Application\ReplayRejectedRecords;
use App\Yack\Catalog\Application\SyncDashboard;
use App\Yack\Catalog\Application\SynchronizeCatalog;
use App\Yack\Catalog\Domain\ResultPage;
use App\Yack\Catalog\Domain\SyncOutcome;
use App\Yack\Catalog\Domain\SyncReport;
use App\Yack\Catalog\Domain\SyncRunFilter;
use App\Yack\Catalog\Domain\SyncRuns;
use App\Yack\Catalog\Domain\SyncSource;
use App\Yack\Catalog\Domain\SyncStatus;
use App\Yack\Catalog\Domain\SyncTrigger;
use App\Yack\Catalog\Infrastructure\Source\SimulatedSourceOutage;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Back-office: dashboard of the PIM/ERP synchronisations (indicators, chart, health of the Yack API,
 * journal of the runs), detail of a run, and the actions: synchronise now, simulate, replay the
 * rejected records once corrected, simulate an outage of the API.
 * Secured by the Sylius admin firewall (path under /%sylius_admin.path_name%).
 */
#[Route('/%sylius_admin.path_name%/yack/synchronisation')]
final class CatalogSyncAdminController extends AbstractController
{
    public const LINES_PER_PAGE = 50;

    /**
     * @param ProductRepositoryInterface<ProductInterface> $products
     */
    public function __construct(
        private readonly SyncDashboard $dashboard,
        private readonly SyncRuns $runs,
        private readonly ReplayRejectedRecords $replay,
        private readonly MessageBusInterface $messageBus,
        private readonly SimulatedSourceOutage $outage,
        #[Autowire(service: 'sylius.repository.product')]
        private readonly ProductRepositoryInterface $products,
        #[Autowire('%app.yack.catalog_feed%')]
        private readonly string $feed,
    ) {
    }

    #[Route('', name: 'app_yack_admin_catalog_sync_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filter = SyncRunFilter::fromQuery($request->query->getString('statut'), $request->query->getString('source'), $request->query->getString('periode'));
        $overview = $this->dashboard->overview();

        return $this->render('admin/yack/catalog_sync/index.html.twig', $overview + [
            'chart' => self::chart($overview['daily']),
            'runs' => $this->dashboard->runs($filter, ResultPage::number($request->query->getString('page', '1'))),
            'filter' => $filter,
            'statuses' => SyncStatus::cases(),
            'sources' => SyncSource::cases(),
            'periods' => SyncRunFilter::PERIODS,
            'feed' => $this->feed,
        ]);
    }

    #[Route('/{id<\d+>}', name: 'app_yack_admin_catalog_sync_show', methods: ['GET'])]
    public function show(int $id, Request $request): Response
    {
        $run = $this->find($id);
        $outcome = SyncOutcome::tryFrom($request->query->getString('resultat'));

        return $this->render('admin/yack/catalog_sync/show.html.twig', [
            'run' => $run,
            'outcome' => $outcome,
            'outcomes' => SyncOutcome::cases(),
            'lines' => $this->runs->lines($run, $outcome, ResultPage::number($request->query->getString('page', '1')), self::LINES_PER_PAGE),
            'replayable' => $this->replay->replayable($run),
            'editable' => ReplayRejectedRecords::EDITABLE,
            'replays' => $this->runs->replaysOf($run),
            'replay_of' => null === $run->getReplayOf() ? null : $this->runs->get($run->getReplayOf()),
        ]);
    }

    #[Route('/{mode<lancer|simulation>}', name: 'app_yack_admin_catalog_sync_run', methods: ['POST'])]
    public function synchronize(string $mode, Request $request): Response
    {
        $this->checkCsrf('yack_catalog_sync_run', $request);
        $report = $this->messageBus
            ->dispatch(new SynchronizeCatalog($this->feed, 'simulation' === $mode, SyncTrigger::BackOffice, $this->operator()))
            ->last(HandledStamp::class)
            ?->getResult();
        if (!$report instanceof SyncReport || null === $report->runId()) {
            throw new \LogicException('The synchronisation returned no recorded report.');
        }
        if (null !== $report->error()) {
            $this->addFlash('error', sprintf('Synchronisation impossible : %s Le catalogue peut être importé à la main (fichier CSV ou XLSX).', $report->error()));
        } else {
            $this->addFlash($report->hasFailures() ? 'warning' : 'success', self::summary($report));
        }

        return $this->redirectToRoute('app_yack_admin_catalog_sync_show', ['id' => $report->runId()]);
    }

    #[Route('/{id<\d+>}/rejouer', name: 'app_yack_admin_catalog_sync_replay', methods: ['POST'])]
    public function replay(int $id, Request $request): Response
    {
        $run = $this->find($id);
        $this->checkCsrf('yack_catalog_sync_replay_' . $id, $request);
        if ([] === $this->replay->replayable($run)) {
            $this->addFlash('warning', 'Cette synchronisation n\'a aucun enregistrement rejeté à rejouer.');

            return $this->redirectToRoute('app_yack_admin_catalog_sync_show', ['id' => $id]);
        }

        $report = ($this->replay)($run, self::corrections($request->request->all('corrections')), $this->operator());
        $this->addFlash($report->hasFailures() ? 'warning' : 'success', self::summary($report));

        return $this->redirectToRoute('app_yack_admin_catalog_sync_show', ['id' => $report->runId()]);
    }

    #[Route('/api/{state<panne|retablie>}', name: 'app_yack_admin_catalog_sync_outage', methods: ['POST'])]
    public function outage(string $state, Request $request): Response
    {
        $this->checkCsrf('yack_catalog_sync_outage', $request);
        $this->outage->switch('panne' === $state);
        $this->addFlash('panne' === $state ? 'warning' : 'success', 'panne' === $state
            ? 'Panne de l\'API Yack simulée : les prochaines synchronisations échoueront. L\'import manuel (CSV ou XLSX) reste disponible.'
            : 'API Yack rétablie : les synchronisations lisent de nouveau le flux.');

        return $this->redirectToRoute('app_yack_admin_catalog_sync_index');
    }

    #[Route('/produit/{code}', name: 'app_yack_admin_catalog_sync_product', methods: ['GET'], requirements: ['code' => '[^/]+'])]
    public function product(string $code): Response
    {
        $product = $this->products->findOneBy(['code' => $code]);
        if (!$product instanceof ProductInterface) {
            throw new NotFoundHttpException(sprintf('Product "%s" not found.', $code));
        }

        return $this->redirectToRoute('sylius_admin_product_show', ['id' => $product->getId()]);
    }

    /**
     * Points of the chart: runs of each day by status, products written.
     *
     * @param list<array{day: string, success: int, partial: int, failure: int, written: int}> $daily
     *
     * @return array{days: list<string>, series: list<array{name: string, type: string, data: list<int>}>}
     */
    public static function chart(array $daily): array
    {
        $series = [];
        foreach (SyncStatus::cases() as $status) {
            $series[] = ['name' => $status->label(), 'type' => 'column', 'data' => array_column($daily, $status->value)];
        }
        $series[] = ['name' => 'Produits écrits', 'type' => 'line', 'data' => array_column($daily, 'written')];

        return ['days' => array_column($daily, 'day'), 'series' => $series];
    }

    /** The counters of a run in one sentence, for the message after an action. */
    public static function summary(SyncReport $report): string
    {
        $counts = [];
        foreach (SyncOutcome::cases() as $outcome) {
            $count = $report->count($outcome);
            $counts[] = sprintf('%d %s', $count, mb_strtolower($outcome->labelFor($count)));
        }

        return sprintf(
            '%s n° %d terminée (%s) : %s.',
            $report->dryRun ? 'Simulation' : 'Synchronisation',
            (int) $report->runId(),
            $report->status()->label(),
            implode(', ', $counts),
        ) . ($report->dryRun ? ' Rien n\'a été écrit dans le catalogue.' : '');
    }

    /**
     * Cells typed on the detail of a run, by line and column, texts only.
     *
     * @param array<mixed> $posted
     *
     * @return array<int, array<string, string>>
     */
    public static function corrections(array $posted): array
    {
        $corrections = [];
        foreach ($posted as $lineId => $cells) {
            if (!is_int($lineId) || !is_array($cells)) {
                continue;
            }
            foreach ($cells as $column => $cell) {
                if (is_string($column) && is_string($cell)) {
                    $corrections[$lineId][$column] = mb_substr($cell, 0, 255);
                }
            }
        }

        return $corrections;
    }

    private function find(int $id): SyncRun
    {
        return $this->runs->get($id) ?? throw new NotFoundHttpException(sprintf('Synchronisation run #%d not found.', $id));
    }

    private function operator(): ?string
    {
        return $this->getUser()?->getUserIdentifier();
    }

    private function checkCsrf(string $id, Request $request): void
    {
        if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
