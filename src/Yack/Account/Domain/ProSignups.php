<?php

declare(strict_types=1);

namespace App\Yack\Account\Domain;

use App\Entity\Customer\Customer;

/**
 * Port: PRO customer accounts (group "pro"), newest first — back-office dashboard.
 */
interface ProSignups
{
    /** @return list<Customer> */
    public function latest(int $limit): array;

    public function countSince(\DateTimeImmutable $since): int;
}
