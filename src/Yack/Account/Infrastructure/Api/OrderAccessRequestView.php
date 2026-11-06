<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Entity\Account\OrderAccessRequest;
use App\Yack\Account\Application\OrderAccessRequestData;

/**
 * PRO customer → "ouverture à la commande en ligne" requests (decided by Yack in the back-office).
 *
 * - GET  /shop/yack/order-access-requests        the customer's requests, newest first
 * - GET  /shop/yack/order-access-requests/draft  form prefilled from the account (PRO only)
 * - POST /shop/yack/order-access-requests        new request (PRO only, 409 while one is pending)
 */
#[ApiResource(
    shortName: 'YackOrderAccessRequest',
    operations: [
        new GetCollection(
            uriTemplate: '/shop/yack/order-access-requests',
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            provider: OrderAccessRequestProvider::class,
            name: 'app_yack_api_shop_order_access_request_index',
        ),
        new Get(
            uriTemplate: '/shop/yack/order-access-requests/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_USER')",
            provider: OrderAccessRequestProvider::class,
            name: 'app_yack_api_shop_order_access_request_show',
        ),
        // Declared after the item operation: the IRI of a request is /order-access-requests/{id}.
        new Get(
            uriTemplate: '/shop/yack/order-access-requests/draft',
            output: OrderAccessRequestData::class,
            security: "is_granted('YACK_REQUEST_ORDER_ACCESS')",
            provider: OrderAccessRequestProvider::class,
            name: OrderAccessRequestProvider::DRAFT_OPERATION,
        ),
        new Post(
            uriTemplate: '/shop/yack/order-access-requests',
            status: 201,
            input: OrderAccessRequestData::class,
            security: "is_granted('YACK_REQUEST_ORDER_ACCESS')",
            processor: OrderAccessRequestProcessor::class,
            name: 'app_yack_api_shop_order_access_request_post',
        ),
    ],
)]
final readonly class OrderAccessRequestView
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public int $id,
        /** pending | accepted | refused */
        public string $status,
        public string $statusLabel,
        public string $company,
        public string $siret,
        public string $fullName,
        public string $email,
        public string $phone,
        public string $address,
        public ?string $message,
        public string $createdAt,
        public ?string $decidedAt,
    ) {
    }

    public static function fromRequest(OrderAccessRequest $request): self
    {
        return new self(
            (int) $request->getId(),
            $request->getStatus()->value,
            $request->getStatus()->label(),
            $request->getCompany(),
            $request->getSiret(),
            $request->getFullName(),
            $request->getEmail(),
            $request->getPhone(),
            $request->getAddress(),
            $request->getMessage(),
            $request->getCreatedAt()->format(\DateTimeInterface::ATOM),
            $request->getDecidedAt()?->format(\DateTimeInterface::ATOM),
        );
    }
}
