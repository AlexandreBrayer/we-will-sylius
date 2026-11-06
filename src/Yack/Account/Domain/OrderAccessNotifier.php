<?php

declare(strict_types=1);

namespace App\Yack\Account\Domain;

use App\Entity\Account\OrderAccessRequest;

/**
 * Port: tells the customer that Yack opened online ordering on their account.
 */
interface OrderAccessNotifier
{
    public function notifyGranted(OrderAccessRequest $request): void;
}
