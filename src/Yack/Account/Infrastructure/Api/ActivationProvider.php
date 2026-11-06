<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Pending activation for the token of the e-mail link (404: unknown or already used token).
 *
 * @implements ProviderInterface<Activation>
 */
final readonly class ActivationProvider implements ProviderInterface
{
    /** @param RepositoryInterface<ShopUserInterface> $shopUserRepository */
    public function __construct(
        #[Autowire(service: 'sylius.repository.shop_user')]
        private RepositoryInterface $shopUserRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Activation
    {
        $token = $uriVariables['token'] ?? null;
        if (!is_string($token) || '' === $token || null === $this->shopUserRepository->findOneBy(['emailVerificationToken' => $token])) {
            throw new NotFoundHttpException('Lien d\'activation invalide ou déjà utilisé.');
        }

        return new Activation($token);
    }
}
