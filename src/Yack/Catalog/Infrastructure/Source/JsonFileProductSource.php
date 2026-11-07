<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Source;

use App\Yack\Catalog\Domain\InvalidSourceProduct;
use App\Yack\Catalog\Domain\ProductFeed;
use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Domain\ProductSource;
use App\Yack\Catalog\Domain\ProductSourceUnavailable;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SourceProduct;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Adapter: reads a JSON export shaped like the Yack PIM/ERP API (see data/yack/*.json)
 * and translates Yack vocabulary into the catalog model.
 *
 * Swapping to the real API = a new ProductSource implementation (HTTP client + same mapping).
 */
#[AsAlias(ProductSource::class)]
final readonly class JsonFileProductSource implements ProductSource
{
    /** Yack spec keys => Sylius attribute codes. */
    private const SPEC_ATTRIBUTES = [
        'power_kw' => 'puissance_kw',
        'scop' => 'scop',
        'refrigerant' => 'fluide_frigorigene',
        'capacity_l' => 'capacite_litres',
    ];

    private const CURRENCY = 'EUR';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function fetch(string $feed): ProductFeed
    {
        $path = str_starts_with($feed, '/') ? $feed : $this->projectDir . '/' . $feed;
        if (!is_readable($path)) {
            throw new ProductSourceUnavailable(sprintf('Yack feed "%s" is not readable.', $path));
        }

        try {
            $payload = json_decode((string) file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ProductSourceUnavailable(sprintf('Yack feed "%s" is not valid JSON: %s', $path, $exception->getMessage()), 0, $exception);
        }

        $records = is_array($payload) ? ($payload['products'] ?? null) : null;
        if (!is_array($records)) {
            throw new ProductSourceUnavailable(sprintf('Yack feed "%s" has no "products" list.', $path));
        }

        $products = [];
        $rejected = [];
        foreach (array_values($records) as $index => $record) {
            $record = SourceRecord::from($record);
            $reference = $record->optionalString('sku') ?? sprintf('#%d', $index);

            try {
                $products[] = $this->map($record, dirname($path));
            } catch (InvalidSourceProduct $exception) {
                $rejected[] = new RejectedRecord($reference, $exception->getMessage());
            }
        }

        $origin = sprintf('%s (%s)', basename($path), SourceRecord::from($payload)->optionalString('exportedAt') ?? 'n/a');

        return new ProductFeed($origin, $products, $rejected);
    }

    private function map(SourceRecord $record, string $baseDir): SourceProduct
    {
        $sku = $record->string('sku');
        $price = $record->record('price');
        $amount = $price?->number('amount');
        if (null === $price || null === $amount) {
            throw new InvalidSourceProduct(sprintf('Product "%s": missing or invalid price.', $sku));
        }
        if (self::CURRENCY !== $price->optionalString('currency')) {
            throw new InvalidSourceProduct(sprintf('Product "%s": unsupported currency "%s".', $sku, $price->text('currency')));
        }

        $attributes = [];
        foreach ($record->scalars('specs') as $key => $value) {
            $attributeCode = self::SPEC_ATTRIBUTES[$key] ?? throw new InvalidSourceProduct(sprintf('Product "%s": unknown spec "%s".', $sku, $key));
            $attributes[$attributeCode] = $value;
        }

        $brand = $record->optionalString('brand');
        $taxonCodes = [];
        if (null !== $brand) {
            $attributes['marque'] = $brand;
            $taxonCodes[] = 'marque_' . (new AsciiSlugger())->slug($brand, '_')->lower()->toString();
        }
        if (null !== ($range = $record->optionalString('range'))) {
            $attributes['gamme'] = $range;
        }
        if (null !== ($oemReference = $record->optionalString('oem_reference'))) {
            $attributes['reference_constructeur'] = $oemReference;
        }

        $image = $record->optionalString('image');
        $type = $record->string('type');
        $kind = ProductKind::tryFrom($type) ?? throw new InvalidSourceProduct(sprintf('Product "%s": unknown type "%s".', $sku, $type));

        return new SourceProduct(
            code: $sku,
            name: $record->string('label'),
            active: 'active' === $record->optionalString('status'),
            kind: $kind,
            mainTaxonCode: $record->string('category'),
            priceInCents: (int) round($amount * 100),
            stock: $record->int('stock'),
            taxonCodes: $taxonCodes,
            attributes: $attributes,
            shortDescription: $record->optionalString('short_description'),
            description: $record->optionalString('description'),
            imagePath: null === $image ? null : $baseDir . '/' . $image,
        );
    }
}
