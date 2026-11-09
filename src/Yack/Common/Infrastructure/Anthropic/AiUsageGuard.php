<?php

declare(strict_types=1);

namespace App\Yack\Common\Infrastructure\Anthropic;

use App\Yack\Common\Domain\AiUnavailable;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Daily cap on real calls to the language model (YACK_AI_DAILY_CAP, 200 when empty), counted
 * for the whole site: the shop is a BFF, every call reaches Sylius from the front server, so a
 * cap per client IP would need the front to forward it (not done in the POC).
 */
final class AiUsageGuard
{
    public const DEFAULT_CAP = 200;

    private readonly int $cap;

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
        #[Autowire(env: 'default::YACK_AI_DAILY_CAP')]
        ?string $cap = null,
        private readonly ?\Closure $clock = null,
    ) {
        $this->cap = null === $cap || '' === trim($cap) ? self::DEFAULT_CAP : max(0, (int) $cap);
    }

    /** Counts one call; throws before the call when today's cap is reached. */
    public function consume(): void
    {
        $item = $this->cache->getItem('yack_ai_calls_'.$this->today()->format('Ymd'));
        $stored = $item->isHit() ? $item->get() : 0;
        $count = \is_int($stored) ? $stored : 0;
        if ($count >= $this->cap) {
            throw AiUnavailable::because('plafond quotidien atteint');
        }

        $item->set($count + 1);
        $item->expiresAfter(2 * 86400);
        $this->cache->save($item);
    }

    private function today(): \DateTimeImmutable
    {
        return null === $this->clock ? new \DateTimeImmutable('today') : ($this->clock)();
    }
}
