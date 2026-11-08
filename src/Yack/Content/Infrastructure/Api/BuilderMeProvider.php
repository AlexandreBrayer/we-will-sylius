<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User\AdminUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<BuilderMeView>
 */
final readonly class BuilderMeProvider implements ProviderInterface
{
    public function __construct(private TokenStorageInterface $tokens)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): BuilderMeView
    {
        $admin = $this->tokens->getToken()?->getUser();
        if (!$admin instanceof AdminUser) {
            throw new AccessDeniedException('Réservé aux administrateurs.');
        }

        return BuilderMeView::of($admin);
    }
}
