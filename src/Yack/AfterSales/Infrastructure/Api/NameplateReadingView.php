<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Infrastructure\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Yack\AfterSales\Application\ApplianceIdentification;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * POST /api/v2/shop/yack/sav/nameplate-readings (public, multipart/form-data, field `photo`, JPEG or PNG,
 * 20 Mo at most, type read on the content): photo of a rating plate → serial number read and looked up.
 * 200 {status: found|unreadable|unknown, serialNumber, model, simulated}; 422 on the `photo` field;
 * 503 when the reading service is unavailable. The photo is never stored.
 */
#[ApiResource(
    shortName: 'YackSavNameplateReading',
    operations: [
        new Post(
            uriTemplate: '/shop/yack/sav/nameplate-readings',
            status: 200,
            inputFormats: ['multipart' => ['multipart/form-data']],
            normalizationContext: ['groups' => ['nameplate_reading:item']],
            input: false,
            read: false,
            deserialize: false,
            validate: false,
            processor: NameplateReadingProcessor::class,
            name: 'app_yack_api_shop_sav_nameplate_reading_post',
        ),
    ],
)]
final readonly class NameplateReadingView
{
    public function __construct(
        /** found | unreadable | unknown */
        #[Groups(['nameplate_reading:item'])]
        public string $status,
        /** Normalised serial number read on the plate (found or unknown), null when unreadable. */
        #[Groups(['nameplate_reading:item'])]
        public ?string $serialNumber,
        /** Model printed on the plate, as read. */
        #[Groups(['nameplate_reading:item'])]
        public ?string $model,
        /** True when the demo reader answered (YACK_AI_ADAPTER=fake). */
        #[Groups(['nameplate_reading:item'])]
        public bool $simulated,
    ) {
    }

    public static function of(ApplianceIdentification $identification): self
    {
        return new self(
            $identification->status->value,
            $identification->serialNumber,
            $identification->model,
            $identification->simulated,
        );
    }
}
