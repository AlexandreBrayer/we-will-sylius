<?php

declare(strict_types=1);

namespace App\Entity\AfterSales;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * Exploded view (vue éclatée) of a model: a drawing with numbered markers, each marker being a spare part.
 */
#[ORM\Entity]
#[ORM\Table(name: 'yack_exploded_view')]
class ExplodedView
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /** @var Collection<int, ExplodedViewItem> */
    #[ORM\OneToMany(targetEntity: ExplodedViewItem::class, mappedBy: 'explodedView', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['marker' => 'ASC'])]
    private Collection $items;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Model::class, inversedBy: 'explodedViews')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Model $model,
        #[ORM\Column(type: 'string', length: 255)]
        private string $name,
        /** Public asset path of the drawing (e.g. yack/sav/evolys-frigo.svg). */
        #[ORM\Column(name: 'image_path', type: 'string', length: 255)]
        private string $imagePath,
        #[ORM\Column(type: 'integer')]
        private int $position = 0,
    ) {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getImagePath(): string
    {
        return $this->imagePath;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    /** @return Collection<int, ExplodedViewItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * @param float $x hotspot abscissa on the drawing, in % of its width
     * @param float $y hotspot ordinate on the drawing, in % of its height
     */
    public function addPart(int $marker, ProductInterface $part, float $x, float $y, int $quantity = 1): ExplodedViewItem
    {
        $item = new ExplodedViewItem($this, $marker, $part, $x, $y, $quantity);
        $this->items->add($item);

        return $item;
    }
}
