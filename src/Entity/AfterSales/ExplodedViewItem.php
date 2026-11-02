<?php

declare(strict_types=1);

namespace App\Entity\AfterSales;

use App\Entity\Product\Product;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * Marker (repère) of an exploded view pointing to the spare part product sold in the shop.
 */
#[ORM\Entity]
#[ORM\Table(name: 'yack_exploded_view_item')]
#[ORM\UniqueConstraint(name: 'yack_exploded_view_item_marker', columns: ['exploded_view_id', 'marker'])]
class ExplodedViewItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: ExplodedView::class, inversedBy: 'items')]
        #[ORM\JoinColumn(name: 'exploded_view_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private ExplodedView $explodedView,
        #[ORM\Column(type: 'integer')]
        private int $marker,
        #[ORM\ManyToOne(targetEntity: Product::class)]
        #[ORM\JoinColumn(name: 'part_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private ProductInterface $part,
        #[ORM\Column(type: 'float')]
        private float $x,
        #[ORM\Column(type: 'float')]
        private float $y,
        #[ORM\Column(type: 'integer')]
        private int $quantity = 1,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExplodedView(): ExplodedView
    {
        return $this->explodedView;
    }

    public function getMarker(): int
    {
        return $this->marker;
    }

    public function getPart(): ProductInterface
    {
        return $this->part;
    }

    public function getX(): float
    {
        return $this->x;
    }

    public function getY(): float
    {
        return $this->y;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }
}
