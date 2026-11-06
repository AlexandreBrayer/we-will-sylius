<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Sylius\Bundle\ApiBundle\Command\Account\VerifyShopUser;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Reuses Sylius' account verification: enables the user and sends the "welcome" e-mail.
 *
 * @implements ProcessorInterface<Activation, null>
 */
final readonly class ActivationProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'sylius.command_bus')]
        private MessageBusInterface $commandBus,
        private ChannelContextInterface $channelContext,
        private LocaleContextInterface $localeContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $this->commandBus->dispatch(new VerifyShopUser(
            (string) $this->channelContext->getChannel()->getCode(),
            $this->localeContext->getLocaleCode(),
            $data->token,
        ));

        return null;
    }
}
