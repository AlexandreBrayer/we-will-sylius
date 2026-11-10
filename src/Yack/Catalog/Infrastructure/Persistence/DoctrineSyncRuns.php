<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Persistence;

use App\Entity\Catalog\SyncRun;
use App\Entity\Catalog\SyncRunLine;
use App\Yack\Catalog\Domain\ResultPage;
use App\Yack\Catalog\Domain\SyncOutcome;
use App\Yack\Catalog\Domain\SyncRunFilter;
use App\Yack\Catalog\Domain\SyncRuns;
use App\Yack\Catalog\Domain\SyncStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/** Adapter: journal of the synchronisations in the tables yack_sync_run and yack_sync_run_line. */
#[AsAlias(SyncRuns::class)]
final readonly class DoctrineSyncRuns implements SyncRuns
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(SyncRun $run): void
    {
        $this->entityManager->persist($run);
        $this->entityManager->flush();
    }

    public function save(SyncRun $run): void
    {
        $this->entityManager->flush();
    }

    public function get(int $id): ?SyncRun
    {
        return $this->entityManager->find(SyncRun::class, $id);
    }

    public function page(SyncRunFilter $filter, \DateTimeImmutable $now, int $page, int $perPage): ResultPage
    {
        $query = $this->runs();
        if (null !== $filter->status) {
            $query->andWhere('run.status = :status')->setParameter('status', $filter->status->value);
        }
        if (null !== $filter->source) {
            $query->andWhere('run.source = :source')->setParameter('source', $filter->source->value);
        }
        if ($filter->simulationsOnly) {
            $query->andWhere('run.dryRun = true');
        }
        if (null !== ($since = $filter->since($now))) {
            $query->andWhere('run.startedAt >= :since')->setParameter('since', $since);
        }
        $query->orderBy('run.startedAt', 'DESC')->addOrderBy('run.id', 'DESC');

        /** @var ResultPage<SyncRun> */
        return $this->paginate($query, $page, $perPage);
    }

    public function since(\DateTimeImmutable $since): array
    {
        /** @var list<SyncRun> */
        return $this->runs()
            ->andWhere('run.dryRun = false')
            ->andWhere('run.startedAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('run.startedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function latest(): ?SyncRun
    {
        return $this->newest($this->runs()->andWhere('run.dryRun = false'));
    }

    public function latestSuccess(): ?SyncRun
    {
        return $this->newest($this->runs()->andWhere('run.dryRun = false')->andWhere('run.status = :status')->setParameter('status', SyncStatus::Success->value));
    }

    public function lines(SyncRun $run, ?SyncOutcome $outcome, int $page, int $perPage): ResultPage
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('line')
            ->from(SyncRunLine::class, 'line')
            ->andWhere('line.run = :run')
            ->setParameter('run', $run);
        self::inFileOrder($query);
        if (null !== $outcome) {
            $query->andWhere('line.outcome = :outcome')->setParameter('outcome', $outcome->value);
        }

        /** @var ResultPage<SyncRunLine> */
        return $this->paginate($query, $page, $perPage);
    }

    public function rejections(SyncRun $run): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('line')
            ->from(SyncRunLine::class, 'line')
            ->andWhere('line.run = :run')
            ->andWhere('line.outcome = :outcome')
            ->setParameter('run', $run)
            ->setParameter('outcome', SyncOutcome::Failed->value);

        /** @var list<SyncRunLine> */
        return self::inFileOrder($query)
            ->getQuery()
            ->getResult();
    }

    /** Lines of an imported file in the order of the file (lines without number last), then in the order of the batch. */
    private static function inFileOrder(QueryBuilder $query): QueryBuilder
    {
        return $query
            ->addSelect('CASE WHEN line.fileLine IS NULL THEN 1 ELSE 0 END AS HIDDEN withoutFileLine')
            ->orderBy('withoutFileLine', 'ASC')
            ->addOrderBy('line.fileLine', 'ASC')
            ->addOrderBy('line.position', 'ASC');
    }

    public function replaysOf(SyncRun $run): array
    {
        /** @var list<SyncRun> */
        return $this->runs()
            ->andWhere('run.replayOf = :run')
            ->setParameter('run', $run->getId())
            ->orderBy('run.startedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function runs(): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()->select('run')->from(SyncRun::class, 'run');
    }

    private function newest(QueryBuilder $query): ?SyncRun
    {
        $run = $query->orderBy('run.startedAt', 'DESC')->addOrderBy('run.id', 'DESC')->setMaxResults(1)->getQuery()->getOneOrNullResult();

        return $run instanceof SyncRun ? $run : null;
    }

    /** @return ResultPage<mixed> */
    private function paginate(QueryBuilder $query, int $page, int $perPage): ResultPage
    {
        $query->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);
        $paginator = new Paginator($query, fetchJoinCollection: false);

        return new ResultPage(array_values(iterator_to_array($paginator)), $page, $perPage, count($paginator));
    }
}
