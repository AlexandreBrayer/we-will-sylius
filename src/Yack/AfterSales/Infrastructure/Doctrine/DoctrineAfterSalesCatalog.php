<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Infrastructure\Doctrine;

use App\Entity\AfterSales\ExplodedView;
use App\Entity\AfterSales\Model;
use App\Entity\AfterSales\SerialNumber;
use App\Yack\AfterSales\Domain\AfterSalesCatalog;
use App\Yack\AfterSales\Domain\SerialNumberFormat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * @extends ServiceEntityRepository<Model>
 */
#[AsAlias(AfterSalesCatalog::class)]
final class DoctrineAfterSalesCatalog extends ServiceEntityRepository implements AfterSalesCatalog
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Model::class);
    }

    public function findModels(string $serialOrServiceCode): array
    {
        $normalized = SerialNumberFormat::normalize($serialOrServiceCode);
        if ('' === $normalized) {
            return [];
        }

        $serialNumber = $this->getEntityManager()->getRepository(SerialNumber::class)->findOneBy(['serial' => $normalized]);
        if (null !== $serialNumber) {
            return array_values($serialNumber->getModels()->toArray());
        }

        $model = $this->findModelByServiceCode($serialOrServiceCode);

        return null === $model ? [] : [$model];
    }

    public function findModelByServiceCode(string $serviceCode): ?Model
    {
        return $this->findOneBy(['serviceCode' => strtoupper(trim($serviceCode))]);
    }

    public function findExplodedView(Model $model, int $explodedViewId): ?ExplodedView
    {
        foreach ($model->getExplodedViews() as $explodedView) {
            if ($explodedView->getId() === $explodedViewId) {
                return $explodedView;
            }
        }

        return null;
    }

    public function findExplodedViewById(int $explodedViewId): ?ExplodedView
    {
        return $this->getEntityManager()->find(ExplodedView::class, $explodedViewId);
    }

    public function installedBase(): array
    {
        /** @var list<SerialNumber> $serials */
        $serials = $this->getEntityManager()->createQueryBuilder()
            ->select('serial', 'model')
            ->from(SerialNumber::class, 'serial')
            ->leftJoin('serial.models', 'model')
            ->orderBy('serial.serial', 'ASC')
            ->getQuery()
            ->getResult();

        return $serials;
    }
}
