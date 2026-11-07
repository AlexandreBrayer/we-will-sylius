<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Cache;

use App\Yack\Content\Domain\ContactRequestThrottle;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Sliding window in the Symfony app cache (symfony/rate-limiter is not installed): the timestamps of the
 * last messages of a sender, at most `maxPerWindow` over `windowSeconds` (config/packages/yack.yaml).
 * Not atomic: two simultaneous messages may both pass, acceptable for a contact form.
 */
#[AsAlias(ContactRequestThrottle::class)]
final readonly class ContactRequestCacheThrottle implements ContactRequestThrottle
{
    public function __construct(
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
        #[Autowire(param: 'app.yack.contact.max_per_window')]
        private int $maxPerWindow,
        #[Autowire(param: 'app.yack.contact.window_seconds')]
        private int $windowSeconds,
    ) {
    }

    public function tryConsume(string $sender): bool
    {
        // Cache keys may not hold ":" (IPv6) nor other reserved characters.
        $item = $this->cache->getItem('yack_contact_' . hash('sha256', $sender));
        $now = time();

        $stored = $item->isHit() ? $item->get() : [];
        $recent = array_values(array_filter(
            \is_array($stored) ? $stored : [],
            fn (mixed $at): bool => \is_int($at) && $at > $now - $this->windowSeconds,
        ));

        if (\count($recent) >= $this->maxPerWindow) {
            return false;
        }

        $recent[] = $now;
        $item->set($recent);
        $item->expiresAfter($this->windowSeconds);
        $this->cache->save($item);

        return true;
    }
}
