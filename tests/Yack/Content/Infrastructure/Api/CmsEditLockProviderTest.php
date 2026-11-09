<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Get;
use App\Yack\Content\Domain\CmsEditLockAction;
use App\Yack\Content\Infrastructure\Api\CmsEditLockProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CmsEditLockProvider::class)]
final class CmsEditLockProviderTest extends TestCase
{
    public function testTheLockAsTheAdministratorWhoAsksSeesIt(): void
    {
        $api = new CmsEditLockApi();
        $api->locking->apply(CmsEditLockAction::Acquire, $api->page, $api->sophie);
        $api->logIn($api->paul);

        $view = (new CmsEditLockProvider($api->locking, $api->targets()))->provide(new Get(), ['slug' => 'offre']);

        $this->assertSame('other', $view->state);
        $this->assertSame('Sophie Martin', $view->holder['name'] ?? null);
        $this->assertSame(1, $api->locks->writes, 'reading takes nothing');
    }
}
