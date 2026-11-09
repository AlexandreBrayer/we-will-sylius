<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Console;

use App\Yack\Catalog\Application\AttachProductPhotos;
use App\Yack\Catalog\Domain\ProductSourceUnavailable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:yack:catalog:photos',
    description: 'Rattache aux produits les photos arrivées après le chargement du catalogue (remplace le visuel de repli, rien d\'autre)',
)]
final class AttachProductPhotosCommand extends Command
{
    public function __construct(private readonly AttachProductPhotos $attachProductPhotos)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('feed', InputArgument::OPTIONAL, 'Flux Yack qui déclare les photos (fichier JSON, relatif à la racine du projet)', 'data/yack/catalog.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $feed = $input->getArgument('feed');
        if (!is_string($feed) || '' === $feed) {
            $output->writeln('<error>Flux Yack manquant.</error>');

            return Command::INVALID;
        }

        try {
            $result = ($this->attachProductPhotos)($feed);
        } catch (ProductSourceUnavailable $exception) {
            $output->writeln(sprintf('<error>photos : flux illisible, %s</error>', $exception->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            'photos rattachées aux produits : %d, déjà en place : %d, sans photo sur le disque : %d',
            count($result->attached),
            $result->alreadyRight,
            $result->withoutPhoto,
        ));

        return Command::SUCCESS;
    }
}
