<?php

declare(strict_types=1);

namespace App\Yack\Payment\Domain;

/**
 * What the customer must do (or has done) to pay an order, shown on the confirmation page,
 * in the confirmation e-mail and in the order detail.
 */
final readonly class PaymentInstruction
{
    private function __construct(
        public PaymentKind $kind,
        public ?BankAccount $bankAccount = null,
        public ?string $reference = null,
        public ?\DateTimeImmutable $dueDate = null,
    ) {
    }

    public static function byBankTransfer(BankAccount $bankAccount, string $reference, \DateTimeImmutable $dueDate): self
    {
        return new self(PaymentKind::BankTransfer, $bankAccount, $reference, $dueDate);
    }

    public static function byCard(): self
    {
        return new self(PaymentKind::Card);
    }

    public function isBankTransfer(): bool
    {
        return PaymentKind::BankTransfer === $this->kind;
    }

    public function isCard(): bool
    {
        return PaymentKind::Card === $this->kind;
    }
}
