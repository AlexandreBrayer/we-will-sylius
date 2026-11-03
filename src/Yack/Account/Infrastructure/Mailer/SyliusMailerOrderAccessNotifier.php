<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Mailer;

use App\Entity\Account\OrderAccessRequest;
use App\Yack\Account\Domain\OrderAccessNotifier;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Sends the Yack e-mail "Votre compte est ouvert à la commande" (code yack_order_access_granted,
 * template configured in config/packages/yack.yaml) through the Sylius mailer.
 */
#[AsAlias(OrderAccessNotifier::class)]
final readonly class SyliusMailerOrderAccessNotifier implements OrderAccessNotifier
{
    public const EMAIL_CODE = 'yack_order_access_granted';

    public function __construct(
        #[Autowire(service: 'sylius.email_sender')]
        private SenderInterface $sender,
        #[Autowire(param: 'app.yack.locale')]
        private string $localeCode,
    ) {
    }

    public function notifyGranted(OrderAccessRequest $request): void
    {
        $email = $request->getCustomer()->getEmail();
        if (null === $email || '' === $email) {
            return;
        }

        $this->sender->send(self::EMAIL_CODE, [$email], [
            'access_request' => $request,
            'localeCode' => $this->localeCode,
        ]);
    }
}
