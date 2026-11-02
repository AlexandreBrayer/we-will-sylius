<?php

declare(strict_types=1);

namespace App\Entity\AfterSales;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Serial number of an installed equipment; a split system resolves to several models
 * (indoor + outdoor unit).
 */
#[ORM\Entity]
#[ORM\Table(name: 'yack_serial_number')]
class SerialNumber
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /** @var Collection<int, Model> */
    #[ORM\ManyToMany(targetEntity: Model::class)]
    #[ORM\JoinTable(name: 'yack_serial_number_model')]
    #[ORM\JoinColumn(name: 'serial_number_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    private Collection $models;

    /** @param list<Model> $models */
    public function __construct(
        #[ORM\Column(type: 'string', length: 64, unique: true)]
        private string $serial,
        array $models,
    ) {
        $this->models = new ArrayCollection($models);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSerial(): string
    {
        return $this->serial;
    }

    /** @return Collection<int, Model> */
    public function getModels(): Collection
    {
        return $this->models;
    }
}
