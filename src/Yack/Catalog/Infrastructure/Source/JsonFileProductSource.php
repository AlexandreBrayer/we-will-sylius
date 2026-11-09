<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Source;

use App\Yack\Catalog\Domain\InvalidSourceProduct;
use App\Yack\Catalog\Domain\OnlineSale;
use App\Yack\Catalog\Domain\ProductFeed;
use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Domain\ProductReference;
use App\Yack\Catalog\Domain\ProductSource;
use App\Yack\Catalog\Domain\ProductSourceUnavailable;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SourceProduct;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Adapter: reads a JSON export shaped like the Yack PIM/ERP API (see data/yack/*.json)
 * and translates Yack vocabulary into the catalog model. Fields without a mapping below
 * (family, ean, source_id) are read by nobody: they stay in the export.
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

    /** Yack text fields => Sylius attribute codes. */
    private const TEXT_ATTRIBUTES = [
        'range' => 'gamme',
        'oem_reference' => 'reference_constructeur',
        'model' => 'modele',
        'typology' => 'typologie',
    ];

    /** Yack list fields => Sylius text attribute codes (values joined by ", "). */
    private const LIST_ATTRIBUTES = [
        'sectors' => 'secteurs',
        'documents' => ProductReference::DOCUMENTATION_ATTRIBUTE,
    ];

    /** Yack yes/no fields => Sylius checkbox attribute codes. */
    private const BOOLEAN_ATTRIBUTES = [
        'sold_online' => OnlineSale::ATTRIBUTE,
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
        foreach (self::TEXT_ATTRIBUTES as $key => $attributeCode) {
            if (null !== ($text = $record->optionalString($key))) {
                $attributes[$attributeCode] = $text;
            }
        }
        foreach (self::LIST_ATTRIBUTES as $key => $attributeCode) {
            if ([] !== ($texts = $record->strings($key))) {
                $attributes[$attributeCode] = implode(ProductReference::DOCUMENT_SEPARATOR, $texts);
            }
        }
        foreach (self::BOOLEAN_ATTRIBUTES as $key => $attributeCode) {
            if (null !== ($flag = $record->optionalBool($key))) {
                $attributes[$attributeCode] = $flag;
            }
        }

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
            imagePath: $this->imagePath($record, $baseDir),
            fallbackImagePath: $this->existingFile($record, 'fallback_image', $baseDir),
        );
    }

    /**
     * Product photo ("image") when its file is there, else the generic visual ("fallback_image"):
     * photos of the Yack catalogue are fetched apart and may be missing. When neither file exists,
     * the declared path is kept so that the catalog rejects the record with the missing file.
     */
    private function imagePath(SourceRecord $record, string $baseDir): ?string
    {
        $declared = null;
        foreach (['image', 'fallback_image'] as $key) {
            $image = $record->optionalString($key);
            if (null === $image) {
                continue;
            }
            $path = $baseDir . '/' . $image;
            if (is_file($path)) {
                return $path;
            }
            $declared ??= $path;
        }

        return $declared;
    }

    private function existingFile(SourceRecord $record, string $key, string $baseDir): ?string
    {
        $file = $record->optionalString($key);

        return null !== $file && is_file($baseDir . '/' . $file) ? $baseDir . '/' . $file : null;
    }
}
