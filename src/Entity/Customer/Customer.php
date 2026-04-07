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
}
