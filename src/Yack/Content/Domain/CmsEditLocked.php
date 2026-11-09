<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/** Save refused: another administrator holds the edit lock of the page or template (D13, answer 409). */
final class CmsEditLocked extends \DomainException
{
    public function __construct(public readonly CmsEditLockState $lock, bool $template)
    {
        parent::__construct(sprintf(
            '%s modifie %s en ce moment : votre enregistrement est refusé pour ne pas écraser son travail. Rouvrez le builder pour voir sa version, ou prenez la main.',
            $lock->holder->name,
            $template ? 'ce template' : 'cette page',
        ));
    }
}
