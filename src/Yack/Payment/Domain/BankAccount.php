<?php

declare(strict_types=1);

namespace App\Yack\Payment\Domain;

/**
 * Yack bank details given to customers paying by bank transfer (RIB).
 */
final readonly class BankAccount
{
    public function __construct(
        public string $holder,
        public string $bank,
        public string $iban,
        public string $bic,
    ) {
        if ('' === trim($holder) || '' === trim($iban) || '' === trim($bic)) {
            throw new \InvalidArgumentException('Bank account holder, IBAN and BIC are required.');
        }
    }
}
