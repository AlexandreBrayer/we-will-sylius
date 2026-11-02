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

        if (!is_array($payload) || !is_array($payload['products'] ?? null)) {
            throw new ProductSourceUnavailable(sprintf('Yack feed "%s" has no "products" list.', $path));
        }

        $products = [];
        $rejected = [];
        foreach (array_values($payload['products']) as $index => $record) {
            $reference = is_array($record) && is_string($record['sku'] ?? null) ? $record['sku'] : sprintf('#%d', $index);

            try {
                $products[] = $this->map(is_array($record) ? $record : [], dirname($path));
            } catch (InvalidSourceProduct $exception) {
                $rejected[] = new RejectedRecord($reference, $exception->getMessage());
            }
        }

        $origin = sprintf('%s (%s)', basename($path), is_string($payload['exportedAt'] ?? null) ? $payload['exportedAt'] : 'n/a');

        return new ProductFeed($origin, $products, $rejected);
    }

    /** @param array<mixed> $record */
    private function map(array $record, string $baseDir): SourceProduct
    {
        $sku = $this->string($record, 'sku');
        $price = $record['price'] ?? null;
        if (!is_array($price) || !is_numeric($price['amount'] ?? null)) {
            throw new InvalidSourceProduct(sprintf('Product "%s": missing or invalid price.', $sku));
        }
        if (self::CURRENCY !== ($price['currency'] ?? null)) {
            throw new InvalidSourceProduct(sprintf('Product "%s": unsupported currency "%s".', $sku, (string) ($price['currency'] ?? '')));
        }

        $attributes = [];
        foreach ((array) ($record['specs'] ?? []) as $key => $value) {
            $attributeCode = self::SPEC_ATTRIBUTES[$key] ?? throw new InvalidSourceProduct(sprintf('Product "%s": unknown spec "%s".', $sku, (string) $key));
            $attributes[$attributeCode] = is_int($value) || is_float($value) ? $value : (string) $value;
        }

        $brand = $this->optionalString($record, 'brand');
        $taxonCodes = [];
        if (null !== $brand) {
            $attributes['marque'] = $brand;
            $taxonCodes[] = 'marque_' . (new AsciiSlugger())->slug($brand, '_')->lower()->toString();
        }
        if (null !== ($range = $this->optionalString($record, 'range'))) {
            $attributes['gamme'] = $range;
        }
        if (null !== ($oemReference = $this->optionalString($record, 'oem_reference'))) {
            $attributes['reference_constructeur'] = $oemReference;
        }

        $image = $this->optionalString($record, 'image');
        $type = $this->string($record, 'type');
        $kind = ProductKind::tryFrom($type) ?? throw new InvalidSourceProduct(sprintf('Product "%s": unknown type "%s".', $sku, $type));

        return new SourceProduct(
            code: $sku,
            name: $this->string($record, 'label'),
            active: 'active' === ($record['status'] ?? null),
            kind: $kind,
            mainTaxonCode: $this->string($record, 'category'),
            priceInCents: (int) round(((float) $price['amount']) * 100),
            stock: (int) ($record['stock'] ?? 0),
            taxonCodes: $taxonCodes,
            attributes: $attributes,
            shortDescription: $this->optionalString($record, 'short_description'),
            description: $this->optionalString($record, 'description'),
            imagePath: null === $image ? null : $baseDir . '/' . $image,
        );
    }

    /** @param array<mixed> $record */
    private function string(array $record, string $key): string
    {
        $value = $record[$key] ?? null;
        if (!is_string($value) || '' === trim($value)) {
            throw new InvalidSourceProduct(sprintf('Field "%s" is missing or empty.', $key));
        }

        return trim($value);
    }

    /** @param array<mixed> $record */
    private function optionalString(array $record, string $key): ?string
    {
        $value = $record[$key] ?? null;

        return is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
