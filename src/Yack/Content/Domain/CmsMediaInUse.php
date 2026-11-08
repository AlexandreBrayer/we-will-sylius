<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/** An image still shown by pages or templates cannot be deleted (409 with the list). */
final class CmsMediaInUse extends \DomainException
{
    /**
     * @param non-empty-list<CmsMediaUsage> $usages
     */
    public function __construct(public readonly array $usages)
    {
        parent::__construct(sprintf(
            'Cette image est utilisée par %s : %s. Retirez-la de ces contenus avant de la supprimer.',
            1 === count($usages) ? 'un contenu' : count($usages) . ' contenus',
            implode(', ', array_map(static fn (CmsMediaUsage $usage): string => $usage->describe(), $usages)),
        ));
    }
}
