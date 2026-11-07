<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Console;

use App\Yack\Catalog\Application\SearchIndexerInterface;
use App\Yack\Catalog\Domain\SearchEngine;
use App\Yack\Catalog\Domain\SearchUnavailable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:yack:search:reindex',
    description: 'Reconstruit les index Meilisearch de la recherche boutique (produits et catégories, réglages compris)',
)]
final class ReindexSearchCommand extends Command
{
    public function __construct(
        private readonly SearchIndexerInterface $searchIndexer,
        #[Autowire('%env(YACK_SEARCH_ENGINE)%')]
        private readonly string $engine,
        #[Autowire('%env(MEILISEARCH_URL)%')]
        private readonly string $meilisearchUrl,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title(sprintf('Index de recherche Meilisearch (%s)', $this->meilisearchUrl));

        try {
            $counts = $this->searchIndexer->reindexAll();
        } catch (SearchUnavailable $exception) {
            $io->error(['Meilisearch ne répond pas : la boutique cherche en base en attendant.', $exception->getMessage()]);

            return Command::FAILURE;
        }

        $io->success(sprintf('%d produits et %d catégories indexés.', $counts['products'], $counts['categories']));
        if (SearchEngine::Meilisearch !== SearchEngine::fromConfig($this->engine)) {
            $io->note('YACK_SEARCH_ENGINE ne vaut pas « meilisearch » : la boutique cherche en base tant que la variable n\'est pas changée (.env.local).');
        }

        return Command::SUCCESS;
    }
}
