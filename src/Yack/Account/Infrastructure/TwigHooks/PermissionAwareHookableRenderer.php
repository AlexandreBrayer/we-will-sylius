<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\TwigHooks;

use Sylius\TwigHooks\Hookable\AbstractHookable;
use Sylius\TwigHooks\Hookable\Metadata\HookableMetadata;
use Sylius\TwigHooks\Hookable\Renderer\HookableRendererInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Lets any Twig hookable declare the permission it requires:
 *
 *     'sylius_shop.shared.product.card':
 *         prices:
 *             configuration: { yack_permission: 'YACK_SEE_PRICES' }
 *
 * The hookable is simply not rendered when the permission is denied, so Sylius templates
 * stay untouched and the business rule lives in a single voter.
 */
#[AsDecorator('sylius_twig_hooks.renderer.hookable')]
final readonly class PermissionAwareHookableRenderer implements HookableRendererInterface
{
    public const CONFIGURATION_KEY = 'yack_permission';

    public function __construct(
        #[AutowireDecorated]
        private HookableRendererInterface $inner,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function render(AbstractHookable $hookable, HookableMetadata $metadata): string
    {
        $permission = $hookable->configuration[self::CONFIGURATION_KEY] ?? null;

        if (is_string($permission) && !$this->authorizationChecker->isGranted($permission)) {
            return '';
        }

        return $this->inner->render($hookable, $metadata);
    }
}
