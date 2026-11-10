<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Console;

use App\Yack\Catalog\Application\SynchronizeCatalog;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SyncOutcome;
use App\Yack\Catalog\Domain\SyncReport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[AsCommand(
    name: 'app:yack:catalog:sync',
    description: 'Synchronise le catalogue depuis le flux PIM/ERP Yack (Yack est maître : création, mise à jour, désactivation)',
)]
final class SyncCatalogCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        #[Autowire('%app.yack.catalog_feed%')]
        private readonly string $defaultFeed,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('feed', InputArgument::OPTIONAL, 'Flux Yack (fichier JSON, relatif à la racine du projet)', $this->defaultFeed);
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulation : calcule les écarts sans rien écrire');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $feed = $input->getArgument('feed');
        if (!is_string($feed) || '' === $feed) {
            $io->error('Flux Yack manquant.');

            return Command::INVALID;
        }
        $dryRun = true === $input->getOption('dry-run');
        $io->title(sprintf('%s catalogue Yack : %s', $dryRun ? 'Simulation de synchronisation' : 'Synchronisation', $feed));

        $report = $this->messageBus->dispatch(new SynchronizeCatalog($feed, $dryRun))->last(HandledStamp::class)?->getResult();
        if (!$report instanceof SyncReport) {
            $io->error('La synchronisation n\'a renvoyé aucun rapport.');

            return Command::FAILURE;
        }
        if (null !== $report->error()) {
            $io->error(sprintf('%s (journal : synchronisation n° %d).', $report->error(), (int) $report->runId()));

            return Command::FAILURE;
        }

        $io->table(
            array_map(static fn (SyncOutcome $outcome): string => $outcome->plural(), SyncOutcome::cases()),
            [array_map($report->count(...), SyncOutcome::cases())],
        );
        if (null !== $report->runId()) {
            $io->text(sprintf('Journal : synchronisation n° %d (back-office, Catalogue > Synchronisation PIM/ERP).', $report->runId()));
        }

        if ($report->hasFailures()) {
            $io->warning('Enregistrements rejetés (détail dans var/log/yack_sync.log) :');
            $io->listing(array_map(
                static fn (RejectedRecord $failure): string => sprintf('%s : %s', $failure->reference, $failure->reason),
                $report->failures(),
            ));

            return Command::FAILURE;
        }

        $io->success(sprintf($dryRun ? 'Flux %s simulé : rien n\'a été écrit.' : 'Flux %s synchronisé.', $report->origin));

        return Command::SUCCESS;
    }
}
