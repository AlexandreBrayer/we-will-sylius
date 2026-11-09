<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Card\Simulated;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Sessions of the simulated provider, kept one day in the cache pool `yack.card_payment_simulator`
 * (config/packages/yack.yaml): the provider's own storage, outside the Yack database.
 */
final readonly class SimulatedSessions
{
    private const LIFETIME = 86400;

    public function __construct(
        #[Autowire(service: 'yack.card_payment_simulator')]
        private CacheItemPoolInterface $pool,
    ) {
    }

    public function get(string $id): ?SimulatedSession
    {
        if (1 !== preg_match('/^[a-f0-9]{32}$/', $id)) {
            return null;
        }

        $item = $this->pool->getItem($id);
        $session = $item->isHit() ? $item->get() : null;

        return $session instanceof SimulatedSession ? $session : null;
    }

    public function save(SimulatedSession $session): void
    {
        $item = $this->pool->getItem($session->id);
        $item->set($session);
        $item->expiresAfter(self::LIFETIME);
        $this->pool->save($item);
    }
}
