<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Source;

use App\Yack\Catalog\Domain\InvalidSourceProduct;
use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Domain\ProductRecordMapper;
use App\Yack\Catalog\Domain\ProductReference;
use App\Yack\Catalog\Domain\SourceProduct;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Adapter: translates a record of the Yack PIM/ERP API (see data/yack/*.json) into the catalog model.
 * Fields without a mapping below (family, ean, source_id) are read by nobody: they stay in the export.
 * The API feed, the manual imports (CSV, XLSX: each line written as such a record) and the replay of
 * rejected records share it.
 */
#[AsAlias(ProductRecordMapper::class)]
final readonly class FeedRecordMapper implements ProductRecordMapper
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

    private const CURRENCY = 'EUR';

    public function map(array $record, ?string $imageDir = null): SourceProduct
    {
        $record = new SourceRecord($record);
        $sku = $record->string('sku');
        $price = $record->record('price');
        $amount = $price?->number('amount');
        if (null === $price || null === $amount) {
            throw new InvalidSourceProduct(sprintf('Produit « %s » : prix absent ou invalide.', $sku));
        }
        if (self::CURRENCY !== $price->optionalString('currency')) {
            throw new InvalidSourceProduct(sprintf('Produit « %s » : devise « %s » non prise en charge.', $sku, $price->text('currency')));
        }

        $attributes = [];
        foreach ($record->scalars('specs') as $key => $value) {
            $attributeCode = self::SPEC_ATTRIBUTES[$key] ?? throw new InvalidSourceProduct(sprintf('Produit « %s » : caractéristique « %s » inconnue.', $sku, $key));
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

        $type = $record->string('type');
        $kind = ProductKind::tryFrom($type) ?? throw new InvalidSourceProduct(sprintf('Produit « %s » : nature « %s » inconnue.', $sku, $type));

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
            imagePath: null === $imageDir ? null : $this->imagePath($record, $imageDir),
            fallbackImagePath: null === $imageDir ? null : $this->existingFile($record, 'fallback_image', $imageDir),
        );
    }

    /**
     * Product photo ("image") when its file is there, else the generic visual ("fallback_image"):
     * photos of the Yack catalogue are fetched apart and may be missing. When neither file exists,
     * the declared path is kept so that the catalog rejects the record with the missing file.
     */
    private function imagePath(SourceRecord $record, string $imageDir): ?string
    {
        $declared = null;
        foreach (['image', 'fallback_image'] as $key) {
            $image = $record->optionalString($key);
            if (null === $image) {
                continue;
            }
            $path = $imageDir . '/' . $image;
            if (is_file($path)) {
                return $path;
            }
            $declared ??= $path;
        }

        return $declared;
    }

    private function existingFile(SourceRecord $record, string $key, string $imageDir): ?string
    {
        $file = $record->optionalString($key);

        return null !== $file && is_file($imageDir . '/' . $file) ? $imageDir . '/' . $file : null;
    }
}
