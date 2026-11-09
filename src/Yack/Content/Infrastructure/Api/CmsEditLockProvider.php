<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Yack\Content\Application\CmsEditLocking;

/**
 * GET of the edit lock of a page or a template (CmsEditLockView), for the administrator who asks.
 *
 * @implements ProviderInterface<CmsEditLockView>
 */
final readonly class CmsEditLockProvider implements ProviderInterface
{
    public function __construct(
        private CmsEditLocking $locking,
        private CmsEditLockTargets $targets,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CmsEditLockView
    {
        $target = $this->targets->target($uriVariables);

        return CmsEditLockView::of($this->locking->status($target, $this->targets->admin()), $target);
    }
}
