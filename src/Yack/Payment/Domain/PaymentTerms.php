<?php

declare(strict_types=1);

namespace App\Yack\Payment\Domain;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Payment terms by payment method code: bank transfer (Yack RIB + reference + due date
 * = order date + N days) or card. Configured in config/packages/yack.yaml
 * (parameter app.yack.payment_terms).
 */
final readonly class PaymentTerms
{
    private BankAccount $bankAccount;

    /** @var array<string, array{kind: PaymentKind, due_days: int}> */
    private array $methods;

    /**
     * @param array{holder: string, bank: string, iban: string, bic: string} $bankAccount
     * @param array<string, array{kind: string, due_days?: int}>            $methods
     */
    public function __construct(
        #[Autowire(param: 'app.yack.payment_terms.bank_account')]
        array $bankAccount,
        #[Autowire(param: 'app.yack.payment_terms.methods')]
        array $methods,
    ) {
        $this->bankAccount = new BankAccount($bankAccount['holder'], $bankAccount['bank'], $bankAccount['iban'], $bankAccount['bic']);

        $terms = [];
        foreach ($methods as $code => $method) {
            $kind = PaymentKind::from($method['kind']);
            $dueDays = $method['due_days'] ?? 0;
            if (PaymentKind::BankTransfer === $kind && $dueDays <= 0) {
                throw new \InvalidArgumentException(sprintf('Payment method "%s": a bank transfer needs a positive due_days.', $code));
            }
            $terms[$code] = ['kind' => $kind, 'due_days' => $dueDays];
        }
        $this->methods = $terms;
    }

    public function instructionFor(string $paymentMethodCode, string $orderNumber, \DateTimeImmutable $orderedAt): ?PaymentInstruction
    {
        $method = $this->methods[$paymentMethodCode] ?? null;
        if (null === $method) {
            return null;
        }

        if (PaymentKind::Card === $method['kind']) {
            return PaymentInstruction::byCard();
        }

        return PaymentInstruction::byBankTransfer(
            $this->bankAccount,
            self::reference($orderNumber),
            $orderedAt->setTime(0, 0)->modify(sprintf('+%d days', $method['due_days'])),
        );
    }

    /**
     * Days granted to pay by bank transfer (due date = order date + N days), shown with the method at checkout:
     * null for a card or a method without terms.
     */
    public function dueDays(string $paymentMethodCode): ?int
    {
        $method = $this->methods[$paymentMethodCode] ?? null;

        return null !== $method && PaymentKind::BankTransfer === $method['kind'] ? $method['due_days'] : null;
    }

    public function isCard(string $paymentMethodCode): bool
    {
        return PaymentKind::Card === ($this->methods[$paymentMethodCode]['kind'] ?? null);
    }

    /** Reference to quote on the transfer, so that Yack can reconcile it with the order. */
    public static function reference(string $orderNumber): string
    {
        return 'YACK-' . $orderNumber;
    }
}
