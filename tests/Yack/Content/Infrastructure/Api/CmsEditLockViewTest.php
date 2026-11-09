<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use App\Yack\Content\Domain\CmsEditLockAction;
use App\Yack\Content\Infrastructure\Api\CmsEditLockView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CmsEditLockView::class)]
final class CmsEditLockViewTest extends TestCase
{
    public function testTheHolderReadsHisLockWithItsToken(): void
    {
        $api = new CmsEditLockApi();
        $status = $api->locking->apply(CmsEditLockAction::Acquire, $api->page, $api->sophie);

        $view = CmsEditLockView::of($status, $api->page);

        $this->assertSame('page', $view->target);
        $this->assertSame('offre', $view->targetId);
        $this->assertSame('mine', $view->state);
        $this->assertSame(['firstName' => 'Sophie', 'lastName' => 'Martin', 'name' => 'Sophie Martin'], $view->holder);
        $this->assertSame('2026-11-08T14:05:00+00:00', $view->since);
        $this->assertSame('2026-11-08T14:05:00+00:00', $view->heartbeatAt);
        $this->assertSame('2026-11-08T14:07:00+00:00', $view->expiresAt);
        $this->assertSame($status->lock?->token, $view->token);
        $this->assertSame('2026-11-08T14:05:00+00:00', $view->now);
        $this->assertSame(30, $view->heartbeatSeconds);
        $this->assertSame(120, $view->timeoutSeconds);
    }

    public function testAnotherAdministratorNeverReadsTheToken(): void
    {
        $api = new CmsEditLockApi();
        $api->locking->apply(CmsEditLockAction::Acquire, $api->template, $api->sophie);

        $view = CmsEditLockView::of($api->locking->status($api->template, $api->paul), $api->template);

        $this->assertSame('template', $view->target);
        $this->assertSame('4', $view->targetId);
        $this->assertSame('other', $view->state);
        $this->assertSame('Sophie Martin', $view->holder['name'] ?? null);
        $this->assertNull($view->token);
    }

    public function testAFreeLockShowsNobody(): void
    {
        $api = new CmsEditLockApi();
        $api->locking->apply(CmsEditLockAction::Acquire, $api->page, $api->sophie);
        $api->clock->sleep(120);

        $view = CmsEditLockView::of($api->locking->status($api->page, $api->sophie), $api->page);

        $this->assertSame('free', $view->state);
        $this->assertNull($view->holder);
        $this->assertNull($view->since);
        $this->assertNull($view->heartbeatAt);
        $this->assertNull($view->expiresAt);
        $this->assertNull($view->token, 'not even to the former holder');
    }
}
