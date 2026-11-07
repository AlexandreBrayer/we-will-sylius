<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Application;

use App\Yack\Catalog\Domain\ProductSearch;
use App\Yack\Catalog\Domain\SearchEngine;
use App\Yack\Catalog\Domain\SearchQuery;
use App\Yack\Catalog\Domain\SearchResult;
use App\Yack\Catalog\Domain\SearchUnavailable;
use App\Yack\Catalog\Domain\Suggestions;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Shop search (results page and suggestions of the header). The configured engine answers;
 * when Meilisearch cannot, the database answers instead and the fallback is logged: a search
 * never fails because the search service is down.
 */
final readonly class SearchCatalog
{
    public const SUGGESTED_PRODUCTS = 6;
    public const SUGGESTED_CATEGORIES = 3;
    public const MAX_SUGGESTIONS = 20;

    /** Suggestions start from this number of characters. */
    public const MIN_SUGGEST_LENGTH = 2;

    public SearchEngine $engine;

    /**
     * @param string $engine YACK_SEARCH_ENGINE (meilisearch | database)
     */
    public function __construct(
        private ProductSearch $meilisearch,
        private ProductSearch $database,
        private LoggerInterface $yackSearchLogger,
        #[Autowire('%env(YACK_SEARCH_ENGINE)%')]
        string $engine,
    ) {
        $this->engine = SearchEngine::fromConfig($engine);
    }

    public function search(SearchQuery $query): SearchResult
    {
        if ($query->isBlank()) {
            return SearchResult::none($this->engine);
        }

        return $this->ask(static fn (ProductSearch $search): SearchResult => $search->search($query), 'search', $query->text);
    }

    public function suggest(string $text, int $productLimit = self::SUGGESTED_PRODUCTS, int $categoryLimit = self::SUGGESTED_CATEGORIES): Suggestions
    {
        $text = (new SearchQuery($text))->text;
        if (mb_strlen($text) < self::MIN_SUGGEST_LENGTH) {
            return Suggestions::none($this->engine);
        }
        $productLimit = max(0, min(self::MAX_SUGGESTIONS, $productLimit));
        $categoryLimit = max(0, min(self::MAX_SUGGESTIONS, $categoryLimit));

        return $this->ask(static fn (ProductSearch $search): Suggestions => $search->suggest($text, $productLimit, $categoryLimit), 'suggest', $text);
    }

    /**
     * @template T of SearchResult|Suggestions
     *
     * @param callable(ProductSearch): T $call
     *
     * @return T
     */
    private function ask(callable $call, string $operation, string $text): SearchResult|Suggestions
    {
        if (SearchEngine::Meilisearch === $this->engine) {
            try {
                return $call($this->meilisearch);
            } catch (SearchUnavailable $exception) {
                $this->yackSearchLogger->warning('search.fallback_to_database', [
                    'operation' => $operation,
                    'query' => $text,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $call($this->database);
    }
}
