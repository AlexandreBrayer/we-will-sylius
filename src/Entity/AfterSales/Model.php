<?php

declare(strict_types=1);

namespace App\Entity\AfterSales;

use App\Entity\Product\Product;
use App\Yack\AfterSales\Infrastructure\Doctrine\DoctrineAfterSalesCatalog;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * A technical model (unit) identified by its Yack ServiceCode, e.g. the outdoor unit of a heat pump.
 */
#[ORM\Entity(repositoryClass: DoctrineAfterSalesCatalog::class)]
#[ORM\Table(name: 'yack_model')]
class Model
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /** @var Collection<int, ExplodedView> */
    #[ORM\OneToMany(targetEntity: ExplodedView::class, mappedBy: 'model', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $explodedViews;

    public function __construct(
        #[ORM\Column(name: 'service_code', type: 'string', length: 32, unique: true)]
        private string $serviceCode,
        #[ORM\Column(type: 'string', length: 255)]
        private string $name,
        #[ORM\Column(type: 'string', length: 128)]
        private string $family,
        #[ORM\ManyToOne(targetEntity: Product::class)]
        #[ORM\JoinColumn(name: 'finished_product_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
        private ?ProductInterface $finishedProduct = null,
    ) {
        $this->explodedViews = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getServiceCode(): string
    {
        return $this->serviceCode;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFamily(): string
    {
        return $this->family;
    }

    public function getFinishedProduct(): ?ProductInterface
    {
        return $this->finishedProduct;
    }

    /** @return Collection<int, ExplodedView> */
    public function getExplodedViews(): Collection
    {
        return $this->explodedViews;
    }

    public function addExplodedView(string $name, string $imagePath): ExplodedView
    {
        $view = new ExplodedView($this, $name, $imagePath, $this->explodedViews->count());
        $this->explodedViews->add($view);

        return $view;
    }

    public function countParts(): int
    {
        $count = 0;
        foreach ($this->explodedViews as $view) {
            $count += $view->getItems()->count();
        }

        return $count;
    }
}
