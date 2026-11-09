<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Infrastructure\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Yack\AfterSales\Application\ApplianceIdentification;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * POST /api/v2/shop/yack/sav/nameplate-readings (public, multipart/form-data, field `photo`, JPEG, PNG or WebP,
 * 20 Mo at most, type read on the content): photo of a rating plate or label → serial number looked up in the
 * installed base, else model reference looked up in the catalog.
 * 200 {status: found|product|unreadable|unknown, serialNumber, reference, model, product, simulated};
 * 422 on the `photo` field; 503 when the reading service is unavailable. The photo is never stored.
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
            // read: with Symfony listeners (Sylius), only the read step sets the request data; there is no provider,
            // so the data is null and the processor gets the photo from the request. With read: false the placeholder
            // controller of API Platform finds no data and fails (500).
            read: true,
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
        /** found | product | unreadable | unknown */
        #[Groups(['nameplate_reading:item'])]
        public string $status,
        /** Normalised serial number read on the plate, null when none was read. */
        #[Groups(['nameplate_reading:item'])]
        public ?string $serialNumber,
        /** Model reference printed on the plate, as read. */
        #[Groups(['nameplate_reading:item'])]
        public ?string $reference,
        /** Model name printed on the plate, as read. */
        #[Groups(['nameplate_reading:item'])]
        public ?string $model,
        /**
         * Catalog product of the reference (status product), null otherwise: its sheet is /produit/{slug}.
         *
         * @var array{code: string, name: string, slug: string, brand: ?string, documents: list<string>}|null
         */
        #[Groups(['nameplate_reading:item'])]
        public ?array $product,
        /** True when the demo reader answered (YACK_AI_ADAPTER=fake). */
        #[Groups(['nameplate_reading:item'])]
        public bool $simulated,
    ) {
    }

    public static function of(ApplianceIdentification $identification): self
    {
        $product = $identification->product;

        return new self(
            $identification->status->value,
            $identification->serialNumber,
            $identification->reference,
            $identification->model,
            null === $product ? null : [
                'code' => $product->code,
                'name' => $product->name,
                'slug' => $product->slug,
                'brand' => $product->brand,
                'documents' => $product->documents,
            ],
            $identification->simulated,
        );
    }
}
