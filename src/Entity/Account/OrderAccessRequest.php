<?php

declare(strict_types=1);

namespace App\Entity\Account;

use App\Entity\Customer\Customer;
use App\Yack\Account\Domain\OrderAccessRequestStatus;
use Doctrine\ORM\Mapping as ORM;

/**
 * "Demande d'accès à la commande en ligne" filed by a PRO customer (no online prices nor
 * ordering) and decided by Yack in the back-office. Accepting it moves the customer to
 * the ECOM group.
 */
#[ORM\Entity]
#[ORM\Table(name: 'yack_order_access_request')]
#[ORM\Index(name: 'yack_order_access_request_status_idx', columns: ['status'])]
class OrderAccessRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 16, enumType: OrderAccessRequestStatus::class)]
    private OrderAccessRequestStatus $status = OrderAccessRequestStatus::Pending;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'decided_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Customer::class)]
        #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private Customer $customer,
        #[ORM\Column(type: 'string', length: 255)]
        private string $company,
        #[ORM\Column(type: 'string', length: 14)]
        private string $siret,
        #[ORM\Column(name: 'full_name', type: 'string', length: 255)]
        private string $fullName,
        #[ORM\Column(type: 'string', length: 255)]
        private string $email,
        #[ORM\Column(type: 'string', length: 32)]
        private string $phone,
        #[ORM\Column(type: 'text')]
        private string $address,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $message,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function accept(\DateTimeImmutable $at): void
    {
        $this->decide(OrderAccessRequestStatus::Accepted, $at);
    }

    public function refuse(\DateTimeImmutable $at): void
    {
        $this->decide(OrderAccessRequestStatus::Refused, $at);
    }

    public function isPending(): bool
    {
        return OrderAccessRequestStatus::Pending === $this->status;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getStatus(): OrderAccessRequestStatus
    {
        return $this->status;
    }

    public function getCompany(): string
    {
        return $this->company;
    }

    public function getSiret(): string
    {
        return $this->siret;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDecidedAt(): ?\DateTimeImmutable
    {
        return $this->decidedAt;
    }

    private function decide(OrderAccessRequestStatus $status, \DateTimeImmutable $at): void
    {
        if (!$this->isPending()) {
            throw new \DomainException(sprintf('Request #%d has already been decided (%s).', $this->id ?? 0, $this->status->value));
        }

        $this->status = $status;
        $this->decidedAt = $at;
    }
}
