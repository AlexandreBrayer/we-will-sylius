<?php

declare(strict_types=1);

namespace App\Yack\Account\Application;

use App\Entity\Account\OrderAccessRequest;

/**
 * Public entry point of Account for the PRO requests to open online ordering still waiting for a decision
 * (ADR 008). Read by Steering (alerts, « Clients et encours »).
 */
interface PendingOrderAccessRequestsInterface
{
    /** @return list<OrderAccessRequest> the most recent first */
    public function pending(): array;
}
