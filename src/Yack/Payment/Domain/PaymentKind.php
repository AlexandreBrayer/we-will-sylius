<?php

declare(strict_types=1);

namespace App\Yack\Payment\Domain;

enum PaymentKind: string
{
    case BankTransfer = 'virement';
    case Card = 'carte';
}
