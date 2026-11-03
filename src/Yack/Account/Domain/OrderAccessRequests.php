<?php

declare(strict_types=1);

namespace App\Yack\Account\Domain;

use App\Entity\Account\OrderAccessRequest;
use App\Entity\Customer\Customer;

/**
 * Port: storage of the PRO → ECOM "order access" requests.
 */
interface OrderAccessRequests
{
    public function add(OrderAccessRequest $request): void;

    public function get(int $id): ?OrderAccessRequest;

    public function latestFor(Customer $customer): ?OrderAccessRequest;

    /** @return list<OrderAccessRequest> */
    public function allFor(Customer $customer): array;

    /** @return list<OrderAccessRequest> */
    public function allNewestFirst(?OrderAccessRequestStatus $status = null): array;

    public function countPending(): int;
}
