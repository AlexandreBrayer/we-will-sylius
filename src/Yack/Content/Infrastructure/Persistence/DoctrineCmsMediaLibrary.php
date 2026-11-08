<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Persistence;

use App\Entity\Content\CmsMedia;
use App\Yack\Content\Domain\CmsMediaLibrary;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(CmsMediaLibrary::class)]
final readonly class DoctrineCmsMediaLibrary implements CmsMediaLibrary
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function exists(string $path): bool
    {
        return null !== $this->entityManager->getRepository(CmsMedia::class)->findOneBy(['path' => $path]);
    }

    public function search(?string $query = null): array
    {
        $builder = $this->entityManager->createQueryBuilder()
            ->select('media')
            ->from(CmsMedia::class, 'media')
            ->orderBy('media.createdAt', 'DESC')
            ->addOrderBy('media.id', 'DESC');

        $query = null === $query ? '' : trim($query);
        if ('' !== $query) {
            $builder
                ->andWhere('LOWER(media.originalName) LIKE :name ESCAPE \'!\'')
                ->setParameter('name', '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($query)) . '%');
        }

        /** @var list<CmsMedia> $media */
        $media = $builder->getQuery()->getResult();

        return $media;
    }

    public function find(int $id): ?CmsMedia
    {
        return $this->entityManager->find(CmsMedia::class, $id);
    }

    public function add(CmsMedia $media): void
    {
        $this->entityManager->persist($media);
        $this->entityManager->flush();
    }

    public function save(CmsMedia $media): void
    {
        $this->entityManager->flush();
    }

    public function remove(CmsMedia $media): void
    {
        $this->entityManager->remove($media);
        $this->entityManager->flush();
    }
}
