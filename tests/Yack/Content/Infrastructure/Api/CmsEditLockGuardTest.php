<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use App\Yack\Content\Domain\CmsEditLockAction;
use App\Yack\Content\Domain\CmsEditLocked;
use App\Yack\Content\Infrastructure\Api\CmsEditLockGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @internal
 */
#[CoversClass(CmsEditLockGuard::class)]
final class CmsEditLockGuardTest extends TestCase
{
    public function testASaveGoesThroughForTheHolder(): void
    {
        $api = new CmsEditLockApi();
        $api->locking->apply(CmsEditLockAction::Acquire, $api->page, $api->sophie);
        $api->logIn($api->sophie);

        $this->assertSame('ok', (new CmsEditLockGuard($api->locking, $api->targets()))->save($api->page, static fn (): string => 'ok'));
    }

    public function testASaveOverTheLockOfAnotherIsA409(): void
    {
        $api = new CmsEditLockApi();
        $api->locking->apply(CmsEditLockAction::Acquire, $api->page, $api->sophie);
        $api->logIn($api->paul);

        try {
            (new CmsEditLockGuard($api->locking, $api->targets()))->save($api->page, static fn (): string => 'jamais');
            $this->fail('ConflictHttpException expected');
        } catch (ConflictHttpException $conflict) {
            $this->assertSame(409, $conflict->getStatusCode());
            $this->assertStringStartsWith('Sophie Martin modifie cette page en ce moment : votre enregistrement est refusé', $conflict->getMessage());
            $this->assertInstanceOf(CmsEditLocked::class, $conflict->getPrevious());
        }
    }

    public function testWithoutAnAdministratorNothingIsRefused(): void
    {
        $api = new CmsEditLockApi();
        $api->locking->apply(CmsEditLockAction::Acquire, $api->page, $api->sophie);

        $this->assertSame('fixtures', (new CmsEditLockGuard($api->locking, $api->targets()))->save($api->page, static fn (): string => 'fixtures'));
    }
}
