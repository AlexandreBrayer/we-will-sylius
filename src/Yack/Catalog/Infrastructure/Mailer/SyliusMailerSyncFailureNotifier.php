<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Mailer;

use App\Entity\Catalog\SyncRun;
use App\Yack\Catalog\Domain\SyncFailureNotifier;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * E-mail « Synchronisation catalogue en échec » (code yack_catalog_sync_failed, template in
 * config/packages/yack.yaml) to the administrators (parameter app.yack.catalog_sync.alert_recipient),
 * with the link to the run in the back-office.
 */
#[AsAlias(SyncFailureNotifier::class)]
final readonly class SyliusMailerSyncFailureNotifier implements SyncFailureNotifier
{
    public const EMAIL_CODE = 'yack_catalog_sync_failed';

    public function __construct(
        #[Autowire(service: 'sylius.email_sender')]
        private SenderInterface $sender,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire(param: 'app.yack.catalog_sync.alert_recipient')]
        private string $recipient,
        #[Autowire(param: 'app.yack.locale')]
        private string $localeCode,
    ) {
    }

    public function notify(SyncRun $run): string
    {
        $this->sender->send(self::EMAIL_CODE, [$this->recipient], [
            'run' => $run,
            'run_url' => $this->urlGenerator->generate('app_yack_admin_catalog_sync_show', ['id' => $run->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            'localeCode' => $this->localeCode,
        ]);

        return $this->recipient;
    }
}
