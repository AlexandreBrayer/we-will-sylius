<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Persistence;

use App\Entity\Content\CmsPage;
use App\Yack\Content\Domain\CmsPages;
use App\Yack\Content\Domain\CmsPageStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(CmsPages::class)]
final readonly class DoctrineCmsPages implements CmsPages
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function findPublished(string $slug): ?CmsPage
    {
        return $this->entityManager->getRepository(CmsPage::class)
            ->findOneBy(['slug' => $slug, 'status' => CmsPageStatus::Published]);
    }

    public function find(string $slug): ?CmsPage
    {
        return $this->entityManager->getRepository(CmsPage::class)->findOneBy(['slug' => $slug]);
    }

    public function published(): array
    {
        return $this->entityManager->getRepository(CmsPage::class)
            ->findBy(['status' => CmsPageStatus::Published], ['title' => 'ASC']);
    }

    public function all(): array
    {
        return $this->entityManager->getRepository(CmsPage::class)
            ->findBy([], ['updatedAt' => 'DESC']);
    }
}
