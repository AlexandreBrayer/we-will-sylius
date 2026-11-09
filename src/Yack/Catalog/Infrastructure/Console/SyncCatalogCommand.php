<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Console;

use App\Yack\Catalog\Application\SynchronizeCatalog;
use App\Yack\Catalog\Domain\SyncOutcome;
use App\Yack\Catalog\Domain\SyncReport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[AsCommand(
    name: 'app:yack:catalog:sync',
    description: 'Synchronise le catalogue depuis le flux PIM/ERP Yack (Yack est maître : création, mise à jour, désactivation)',
)]
final class SyncCatalogCommand extends Command
{
    public function __construct(private readonly MessageBusInterface $messageBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('feed', InputArgument::OPTIONAL, 'Flux Yack (fichier JSON, relatif à la racine du projet)', 'data/yack/api_feed_update.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $feed = $input->getArgument('feed');
        if (!is_string($feed) || '' === $feed) {
            $io->error('Flux Yack manquant.');

            return Command::INVALID;
        }
        $io->title(sprintf('Synchronisation catalogue Yack — %s', $feed));

        try {
            $envelope = $this->messageBus->dispatch(new SynchronizeCatalog($feed));
        } catch (HandlerFailedException $exception) {
            $io->error($exception->getPrevious()?->getMessage() ?? $exception->getMessage());

            return Command::FAILURE;
        }

        $report = $envelope->last(HandledStamp::class)?->getResult();
        if (!$report instanceof SyncReport) {
            $io->error('La synchronisation n\'a renvoyé aucun rapport.');

            return Command::FAILURE;
        }

        $io->table(
            ['Créés', 'Mis à jour', 'Désactivés', 'En erreur'],
            [[
                $report->count(SyncOutcome::Created),
                $report->count(SyncOutcome::Updated),
                $report->count(SyncOutcome::Disabled),
                $report->count(SyncOutcome::Failed),
            ]],
        );

        if ($report->hasFailures()) {
            $io->warning('Enregistrements rejetés (détail dans var/log/yack_sync.log) :');
            $io->listing(array_map(
                static fn ($failure): string => sprintf('%s — %s', $failure->reference, $failure->reason),
                $report->failures(),
            ));

            return Command::FAILURE;
        }

        $io->success(sprintf('Flux %s synchronisé.', $report->origin));

        return Command::SUCCESS;
    }
}
