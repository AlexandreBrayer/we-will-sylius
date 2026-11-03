<?php

declare(strict_types=1);

namespace App\Entity\Customer;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Customer as BaseCustomer;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_customer')]
class Customer extends BaseCustomer
{
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $escompteRate = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $escompteDays = null;

    /** Raison sociale (comptes professionnels Yack). */
    #[ORM\Column(name: 'company', type: 'string', length: 255, nullable: true)]
    private ?string $company = null;

    #[ORM\Column(name: 'siret', type: 'string', length: 14, nullable: true)]
    private ?string $siret = null;

    /** N° de TVA intracommunautaire (facultatif, saisi à l'inscription). */
    #[ORM\Column(name: 'vat_number', type: 'string', length: 20, nullable: true)]
    private ?string $vatNumber = null;

    public function getEscompteRate(): ?float
    {
        return $this->escompteRate;
    }

    public function setEscompteRate(?float $escompteRate): void
    {
        $this->escompteRate = $escompteRate;
    }

    public function getEscompteDays(): ?int
    {
        return $this->escompteDays;
    }

    public function setEscompteDays(?int $escompteDays): void
    {
        $this->escompteDays = $escompteDays;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): void
    {
        $this->company = $company;
    }

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(?string $siret): void
    {
        $this->siret = $siret;
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(?string $vatNumber): void
    {
        $this->vatNumber = $vatNumber;
    }
}
