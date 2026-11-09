<?php

declare(strict_types=1);

namespace App\Yack\Account\Application;

use App\Yack\Account\Domain\OrderAccessRequests;
use App\Yack\Account\Domain\OrderAccessRequestStatus;

final readonly class PendingOrderAccessRequests implements PendingOrderAccessRequestsInterface
{
    public function __construct(private OrderAccessRequests $requests)
    {
    }

    public function pending(): array
    {
        return $this->requests->allNewestFirst(OrderAccessRequestStatus::Pending);
    }
}
