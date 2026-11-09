<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * An image still shown by pages, templates or reusable sections cannot be deleted (409 with the list).
 * The blocks of a section never change: the message says it is deleted from « Mes sections ».
 */
final class CmsMediaInUse extends \DomainException
{
    /**
     * @param non-empty-list<CmsMediaUsage> $usages
     */
    public function __construct(public readonly array $usages)
    {
        $sections = [] !== array_filter($usages, static fn (CmsMediaUsage $usage): bool => CmsMediaUsage::SECTION === $usage->kind);

        parent::__construct(sprintf(
            'Cette image est utilisée par %s : %s. Retirez-la de ces contenus avant de la supprimer%s.',
            1 === count($usages) ? 'un contenu' : count($usages) . ' contenus',
            implode(', ', array_map(static fn (CmsMediaUsage $usage): string => $usage->describe(), $usages)),
            $sections ? ' (une section ne se modifie pas : supprimez-la dans « Mes sections », onglet Blocs du builder)' : '',
        ));
    }
}
