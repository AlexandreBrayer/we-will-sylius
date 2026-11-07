<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Domain;

/**
 * Port: the passages relevant to a question, best first, at most `$limit`, only from the notices
 * of `$question->productCode` when the question comes from a product page. An empty list means
 * "nothing relevant": the assistant then answers without calling any model.
 *
 * Default: lexical retrieval in PHP (Application\RetrievePassages). A semantic or hybrid engine
 * (Typesense, Meilisearch) replaces it with one adapter implementing this interface.
 */
interface PassageRetriever
{
    /** @return list<Passage> */
    public function retrieve(Question $question, int $limit): array;
}
