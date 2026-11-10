<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Application;

use App\Yack\Catalog\Application\SearchIndexerInterface;

/** Hand-written fake of the interface: records the refreshed codes. */
final class FakeSearchIndexer implements SearchIndexerInterface
{
    /** @var list<list<string>> */
    public array $refreshed = [];

    public function reindexAll(): array
    {
        return ['products' => 0, 'categories' => 0];
    }

    public function refresh(array $codes): void
    {
        $this->refreshed[] = $codes;
    }
}
