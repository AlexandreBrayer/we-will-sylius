<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Persistence;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsRevision;
use App\Entity\Content\CmsTemplate;
use App\Yack\Content\Domain\CmsPageStatus;
use App\Yack\Content\Domain\CmsRevisionAuthor;
use App\Yack\Content\Domain\CmsRevisions;
use App\Yack\Content\Domain\CmsRevisionSummary;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * The list reads the columns it shows, never `content` (a GrapesJS project per revision). Newest first is
 * the order of the saves: the identifier, never equal, breaks the ties of a date stored to the second.
 */
#[AsAlias(CmsRevisions::class)]
final readonly class DoctrineCmsRevisions implements CmsRevisions
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(CmsRevision $revision): void
    {
        $this->entityManager->persist($revision);
    }

    public function find(int $id): ?CmsRevision
    {
        return $this->entityManager->find(CmsRevision::class, $id);
    }

    public function summaries(CmsPage|CmsTemplate $target): array
    {
        if (null === $target->getId()) {
            return [];
        }

        /** @var list<array{id: int|string, createdAt: \DateTimeImmutable, status: CmsPageStatus|string, title: string, blockCount: int|string, authorFirstName: ?string, authorLastName: ?string, authorName: ?string}> $rows */
        $rows = $this->ofTarget($target)
            ->select('revision.id', 'revision.createdAt', 'revision.status', 'revision.title', 'revision.blockCount', 'revision.authorFirstName', 'revision.authorLastName', 'revision.authorName')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): CmsRevisionSummary => new CmsRevisionSummary(
            (int) $row['id'],
            $row['createdAt'],
            CmsRevisionAuthor::stored($row['authorFirstName'], $row['authorLastName'], $row['authorName']),
            $row['status'] instanceof CmsPageStatus ? $row['status'] : CmsPageStatus::from($row['status']),
            $row['title'],
            (int) $row['blockCount'],
        ), $rows);
    }

    public function removeOldest(CmsPage|CmsTemplate $target, int $keep): int
    {
        if (null === $target->getId()) {
            return 0;
        }

        /** @var list<int|string> $ids */
        $ids = $this->ofTarget($target)
            ->select('revision.id')
            ->setFirstResult(max(0, $keep))
            ->getQuery()
            ->getSingleColumnResult();

        foreach ($ids as $id) {
            // A reference: the content of the removed revision is never loaded.
            $revision = $this->entityManager->getReference(CmsRevision::class, (int) $id);
            if (null !== $revision) {
                $this->entityManager->remove($revision);
            }
        }

        return \count($ids);
    }

    private function ofTarget(CmsPage|CmsTemplate $target): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->from(CmsRevision::class, 'revision')
            ->andWhere($target instanceof CmsPage ? 'revision.page = :target' : 'revision.template = :target')
            ->setParameter('target', $target->getId())
            ->orderBy('revision.createdAt', 'DESC')
            ->addOrderBy('revision.id', 'DESC');
    }
}
