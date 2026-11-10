<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * What a record of Yack changes on a product of the shop, field by field: the outcome of the record
 * (created, updated, disabled, unchanged) follows from the difference, so a simulation tells exactly
 * what the synchronisation would write.
 *
 * Compared fields: those the catalog mirrors (SyliusProductCatalog): name, status, price, stock, main
 * category, attributes sent by Yack (an attribute Yack does not send is left as is), descriptions.
 */
final readonly class ProductChange
{
    /** Attribute that carries the kind of product (ProductKind::label()). */
    public const KIND_ATTRIBUTE = 'type_article';

    /** Labels of the attributes of the Yack catalog (Sylius attribute codes). */
    private const ATTRIBUTE_LABELS = [
        self::KIND_ATTRIBUTE => 'Nature',
        'marque' => 'Marque',
        'gamme' => 'Gamme',
        'modele' => 'Modèle',
        'typologie' => 'Typologie',
        'reference_constructeur' => 'Référence constructeur',
        'puissance_kw' => 'Puissance (kW)',
        'scop' => 'SCOP',
        'fluide_frigorigene' => 'Fluide frigorigène',
        'capacite_litres' => 'Capacité (L)',
        'secteurs' => 'Secteurs',
        ProductReference::DOCUMENTATION_ATTRIBUTE => 'Documents',
    ];

    /**
     * @param list<FieldChange> $fields
     */
    public function __construct(
        public string $code,
        public string $name,
        public SyncOutcome $outcome,
        public array $fields = [],
    ) {
    }

    public static function compare(?CatalogProduct $current, SourceProduct $source): self
    {
        $before = null === $current ? [] : self::values($current->name, $current->active, $current->priceInCents, $current->stock, $current->mainTaxonCode, $current->attributes, $current->shortDescription, $current->description);
        $after = self::values(
            $source->name,
            $source->active,
            $source->priceInCents,
            $source->stock,
            $source->mainTaxonCode,
            [self::KIND_ATTRIBUTE => $source->kind->label()] + $source->attributes,
            $source->shortDescription,
            $source->description,
        );

        $fields = [];
        foreach ($after as $field => [$label, $value]) {
            $old = $before[$field][1] ?? null;
            if ($old !== $value) {
                $fields[] = new FieldChange($field, $label, $old, $value);
            }
        }

        $outcome = match (true) {
            null === $current => SyncOutcome::Created,
            [] === $fields => SyncOutcome::Unchanged,
            $current->active && !$source->active => SyncOutcome::Disabled,
            default => SyncOutcome::Updated,
        };

        return new self($source->code, $source->name, $outcome, $fields);
    }

    /** Label of an attribute of the catalog, its code when it has none. */
    public static function attributeLabel(string $code): string
    {
        return self::ATTRIBUTE_LABELS[$code] ?? $code;
    }

    /** Price as shown in the back-office: « 1 699,90 € ». */
    public static function price(int $cents): string
    {
        return number_format($cents / 100, 2, ',', "\u{a0}") . "\u{a0}€";
    }

    /** Text of a stored value: a decimal number with a comma, a whole float without decimals. */
    public static function text(string|int|float|bool $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'oui' : 'non',
            is_float($value) => str_replace('.', ',', rtrim(rtrim(sprintf('%.4F', $value), '0'), '.')),
            default => trim((string) $value),
        };
    }

    public function fieldCount(): int
    {
        return count($this->fields);
    }

    /**
     * Shown value of each compared field, keyed by field (attributes: `attribute.<code>`).
     *
     * @param array<string, string|int|float|bool> $attributes
     *
     * @return array<string, array{string, string|null}> field => [label, value]
     */
    private static function values(string $name, bool $active, ?int $priceInCents, ?int $stock, ?string $mainTaxonCode, array $attributes, ?string $shortDescription, ?string $description): array
    {
        $values = [
            'name' => ['Nom', self::blankToNull($name)],
            'active' => ['Statut', $active ? 'Actif' : 'Inactif'],
            'price' => ['Prix HT', null === $priceInCents ? null : self::price($priceInCents)],
            'stock' => ['Stock', null === $stock ? null : (string) $stock],
            'category' => ['Catégorie', self::blankToNull($mainTaxonCode)],
        ];
        foreach ($attributes as $code => $value) {
            $values['attribute.' . $code] = [self::attributeLabel($code), self::blankToNull(self::text($value))];
        }
        $values['short_description'] = ['Description courte', self::blankToNull($shortDescription)];
        $values['description'] = ['Description', self::blankToNull($description)];

        return $values;
    }

    private static function blankToNull(?string $text): ?string
    {
        return null === $text || '' === trim($text) ? null : trim($text);
    }
}
