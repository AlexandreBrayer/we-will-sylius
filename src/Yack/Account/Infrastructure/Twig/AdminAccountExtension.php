<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Twig;

use App\Entity\Account\OrderAccessRequest;
use App\Entity\Customer\Customer;
use App\Yack\Account\Domain\OrderAccessRequests;
use App\Yack\Account\Domain\ProSignups;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Back-office data for the Yack blocks: dashboard (pending requests, PRO signups)
 * and customer page (order access request history).
 */
final class AdminAccountExtension extends AbstractExtension
{
    public function __construct(
        private readonly OrderAccessRequests $requests,
        private readonly ProSignups $proSignups,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('yack_pending_order_access_requests_count', $this->requests->countPending(...)),
            new TwigFunction('yack_order_access_requests_for', $this->requestsFor(...)),
            new TwigFunction('yack_latest_pro_signups', $this->proSignups->latest(...)),
            new TwigFunction('yack_pro_signups_count_since', $this->proSignupsCountSince(...)),
        ];
    }

    /** @return list<OrderAccessRequest> */
    public function requestsFor(mixed $customer): array
    {
        return $customer instanceof Customer ? $this->requests->allFor($customer) : [];
    }

    public function proSignupsCountSince(string $since): int
    {
        return $this->proSignups->countSince(new \DateTimeImmutable($since));
    }
}
