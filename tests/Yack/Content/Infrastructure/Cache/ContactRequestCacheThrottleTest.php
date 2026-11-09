<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Cache;

use App\Yack\Content\Infrastructure\Cache\ContactRequestCacheThrottle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Needs symfony/cache (vendor): runs with PHPUnit on the Mac, not on the agents' bench.
 *
 * @internal
 */
#[CoversClass(ContactRequestCacheThrottle::class)]
final class ContactRequestCacheThrottleTest extends TestCase
{
    public function testTheSenderIsStoppedAtTheLimit(): void
    {
        $throttle = new ContactRequestCacheThrottle(new ArrayAdapter(), 2, 900);

        $this->assertTrue($throttle->tryConsume('203.0.113.7'));
        $this->assertTrue($throttle->tryConsume('203.0.113.7'));
        $this->assertFalse($throttle->tryConsume('203.0.113.7'));
    }

    public function testEachSenderHasItsOwnCount(): void
    {
        $throttle = new ContactRequestCacheThrottle(new ArrayAdapter(), 1, 900);

        $this->assertTrue($throttle->tryConsume('203.0.113.7'));
        $this->assertTrue($throttle->tryConsume('2001:db8::1'));
        $this->assertFalse($throttle->tryConsume('2001:db8::1'));
    }

    public function testMessagesOutsideTheWindowNoLongerCount(): void
    {
        $cache = new ArrayAdapter();
        $item = $cache->getItem('yack_contact_' . hash('sha256', '203.0.113.7'));
        $item->set([time() - 1000, 'pas un horodatage']);
        $cache->save($item);

        $this->assertTrue((new ContactRequestCacheThrottle($cache, 1, 900))->tryConsume('203.0.113.7'));
    }
}
