<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Persistence;

use App\Entity\Content\CmsSection;
use App\Yack\Content\Domain\CmsSections;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(CmsSections::class)]
final readonly class DoctrineCmsSections implements CmsSections
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function all(): array
    {
        /** @var list<CmsSection> $sections */
        $sections = $this->entityManager->createQueryBuilder()
            ->select('section')
            ->from(CmsSection::class, 'section')
            ->orderBy('LOWER(section.name)', 'ASC')
            ->addOrderBy('section.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $sections;
    }

    public function find(int $id): ?CmsSection
    {
        return $this->entityManager->find(CmsSection::class, $id);
    }

    public function add(CmsSection $section): void
    {
        $this->entityManager->persist($section);
        $this->entityManager->flush();
    }

    public function save(CmsSection $section): void
    {
        $this->entityManager->flush();
    }

    public function remove(CmsSection $section): void
    {
        $this->entityManager->remove($section);
        $this->entityManager->flush();
    }
}
