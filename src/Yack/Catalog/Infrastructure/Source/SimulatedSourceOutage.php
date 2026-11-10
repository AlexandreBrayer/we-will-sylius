<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Source;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Outage of the Yack PIM/ERP API simulated from the back-office (dashboard of the synchronisations), to
 * show the failed run, the alert and the manual import: a flag in the cache pool
 * `yack.catalog_source_simulator` (config/packages/yack.yaml), outside the Yack database.
 */
final readonly class SimulatedSourceOutage
{
    private const KEY = 'outage';

    public function __construct(
        #[Autowire(service: 'yack.catalog_source_simulator')]
        private CacheItemPoolInterface $pool,
    ) {
    }

    public function isOn(): bool
    {
        $item = $this->pool->getItem(self::KEY);

        return $item->isHit() && true === $item->get();
    }

    public function switch(bool $on): void
    {
        if (!$on) {
            $this->pool->deleteItem(self::KEY);

            return;
        }

        $item = $this->pool->getItem(self::KEY);
        $item->set(true);
        $this->pool->save($item);
    }
}
