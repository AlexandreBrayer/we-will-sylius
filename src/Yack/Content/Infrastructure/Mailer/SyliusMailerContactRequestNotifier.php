<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Mailer;

use App\Yack\Content\Domain\ContactRequest;
use App\Yack\Content\Domain\ContactRequestNotifier;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * E-mail « Nouveau message de contact » (code yack_contact_request, template in config/packages/yack.yaml)
 * to Yack's address (parameter app.yack.contact.recipient), answerable to the sender (Reply-To).
 */
#[AsAlias(ContactRequestNotifier::class)]
final readonly class SyliusMailerContactRequestNotifier implements ContactRequestNotifier
{
    public const EMAIL_CODE = 'yack_contact_request';

    public function __construct(
        #[Autowire(service: 'sylius.email_sender')]
        private SenderInterface $sender,
        #[Autowire(param: 'app.yack.contact.recipient')]
        private string $recipient,
        #[Autowire(param: 'app.yack.locale')]
        private string $localeCode,
    ) {
    }

    public function notify(ContactRequest $request): void
    {
        // SenderInterface::send($code, $recipients, $data, $attachments, $replyTo)
        $this->sender->send(
            self::EMAIL_CODE,
            [$this->recipient],
            ['contact_request' => $request, 'localeCode' => $this->localeCode],
            [],
            [$request->email],
        );
    }
}
