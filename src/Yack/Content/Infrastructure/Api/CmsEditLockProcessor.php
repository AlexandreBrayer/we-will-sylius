<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Yack\Content\Application\CmsEditLocking;
use App\Yack\Content\Domain\CmsEditLockAction;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * POST of the edit lock of a page or a template: acquire, heartbeat, takeover or release
 * (Application\CmsEditLocking), answered with the lock after the operation; 422 on `action` otherwise.
 *
 * @implements ProcessorInterface<CmsEditLockInput, CmsEditLockView>
 */
final readonly class CmsEditLockProcessor implements ProcessorInterface
{
    public function __construct(
        private CmsEditLocking $locking,
        private CmsEditLockTargets $targets,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CmsEditLockView
    {
        $target = $this->targets->target($uriVariables);
        $action = CmsEditLockAction::tryFrom($data->action ?? '');
        if (null === $action) {
            throw new ValidationException(new ConstraintViolationList([new ConstraintViolation(
                'Action inconnue : acquire, heartbeat, takeover ou release.',
                null,
                [],
                $data,
                'action',
                $data->action,
            )]));
        }
        $token = \is_string($data->token) && '' !== $data->token ? $data->token : null;

        return CmsEditLockView::of($this->locking->apply($action, $target, $this->targets->admin(), $token), $target);
    }
}
