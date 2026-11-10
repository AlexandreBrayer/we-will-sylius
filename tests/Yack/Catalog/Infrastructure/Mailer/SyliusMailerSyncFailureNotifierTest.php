<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Mailer;

use App\Entity\Catalog\SyncRun;
use App\Yack\Catalog\Domain\SyncContext;
use App\Yack\Catalog\Infrastructure\Mailer\SyliusMailerSyncFailureNotifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[CoversClass(SyliusMailerSyncFailureNotifier::class)]
final class SyliusMailerSyncFailureNotifierTest extends TestCase
{
    public function testTheAdministratorsReceiveTheFailedRunWithItsLinkInTheBackOffice(): void
    {
        $run = SyncRun::failed(new SyncContext(), 'catalog.json', 'API injoignable.', new \DateTimeImmutable('2026-11-10 06:00'), new \DateTimeImmutable('2026-11-10 06:00'));
        (new \ReflectionProperty(SyncRun::class, 'id'))->setValue($run, 42);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects($this->once())->method('generate')
            ->with('app_yack_admin_catalog_sync_show', ['id' => 42], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://yack.test/admin/catalogue/synchronisation/42');
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects($this->once())->method('send')->with(
            'yack_catalog_sync_failed',
            ['catalogue@yack.test'],
            ['run' => $run, 'run_url' => 'https://yack.test/admin/catalogue/synchronisation/42', 'localeCode' => 'fr_FR'],
        );

        $recipient = (new SyliusMailerSyncFailureNotifier($sender, $urls, 'catalogue@yack.test', 'fr_FR'))->notify($run);

        $this->assertSame('catalogue@yack.test', $recipient);
    }
}
