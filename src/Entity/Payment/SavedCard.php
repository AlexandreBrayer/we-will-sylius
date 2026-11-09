<?php

declare(strict_types=1);

namespace App\Entity\Payment;

use App\Entity\Customer\Customer;
use App\Yack\Payment\Domain\Card\CardBrand;
use App\Yack\Payment\Domain\Card\MaskedCard;
use App\Yack\Payment\Domain\Card\WalletCard;
use Doctrine\ORM\Mapping as ORM;

/**
 * Card saved by a customer for one-click payments: token of the payment service provider and
 * masked card (brand, last four digits, expiry). The card number never reaches Yack.
 */
#[ORM\Entity]
#[ORM\Table(name: 'yack_saved_card')]
class SavedCard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 16, enumType: CardBrand::class)]
    private CardBrand $brand;

    #[ORM\Column(type: 'string', length: 4)]
    private string $last4;

    #[ORM\Column(name: 'expiry_month', type: 'smallint')]
    private int $expiryMonth;

    #[ORM\Column(name: 'expiry_year', type: 'smallint')]
    private int $expiryYear;

    #[ORM\Column(type: 'string', length: 64)]
    private string $token;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Customer::class)]
        #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private Customer $customer,
        WalletCard $walletCard,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->renew($walletCard);
    }

    /** Same card saved again: the provider issued a new token. */
    public function renew(WalletCard $walletCard): void
    {
        $this->brand = $walletCard->card->brand;
        $this->last4 = $walletCard->card->last4;
        $this->expiryMonth = $walletCard->card->expiryMonth;
        $this->expiryYear = $walletCard->card->expiryYear;
        $this->token = $walletCard->token;
    }

    public function isSameCardAs(MaskedCard $card): bool
    {
        return $this->brand === $card->brand
            && $this->last4 === $card->last4
            && $this->expiryMonth === $card->expiryMonth
            && $this->expiryYear === $card->expiryYear;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function card(): MaskedCard
    {
        return new MaskedCard($this->brand, $this->last4, $this->expiryMonth, $this->expiryYear);
    }

    public function walletCard(): WalletCard
    {
        return new WalletCard($this->token, $this->card());
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
