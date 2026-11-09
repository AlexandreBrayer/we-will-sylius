<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Persistence;

use App\Yack\Content\Domain\CmsContentTransaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/** One database transaction around the work and its flush (rolled back when either throws). */
#[AsAlias(CmsContentTransaction::class)]
final readonly class DoctrineCmsContentTransaction implements CmsContentTransaction
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function run(callable $work): mixed
    {
        return $this->entityManager->wrapInTransaction(static fn (): mixed => $work());
    }
}
