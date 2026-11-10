<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain\Import;

use App\Yack\Catalog\Domain\InvalidSourceProduct;
use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Domain\ProductReference;
use Symfony\Component\String\UnicodeString;

/**
 * Column contract of the manual import (CSV, XLSX) and of its templates: each column is a field of the
 * Yack PIM/ERP API feed (`path`, dot for a nested field), so an imported line becomes a record of the
 * feed and goes through the same mapping. A header is recognised in French or in English, accents,
 * case, punctuation and units in brackets aside (« Prix HT (€) », « price »).
 */
enum ImportColumn: string
{
    case Code = 'code';
    case Name = 'nom';
    case Kind = 'nature';
    case Active = 'actif';
    case Category = 'categorie';
    case Brand = 'marque';
    case Price = 'prix_ht';
    case Stock = 'stock';
    case Range = 'gamme';
    case Model = 'modele';
    case Typology = 'typologie';
    case OemReference = 'reference_constructeur';
    case Power = 'puissance_kw';
    case Refrigerant = 'fluide_frigorigene';
    case Scop = 'scop';
    case Capacity = 'capacite_l';
    case Sectors = 'secteurs';
    case Documents = 'documents';
    case ShortDescription = 'description_courte';
    case Description = 'description';

    private const TRUE_VALUES = ['oui', 'o', 'yes', 'y', '1', 'true', 'vrai', 'actif', 'active', 'x'];

    private const FALSE_VALUES = ['non', 'n', 'no', '0', 'false', 'faux', 'inactif', 'inactive'];

    private const KIND_VALUES = [
        'finished' => ProductKind::Finished, 'produit fini' => ProductKind::Finished, 'produit' => ProductKind::Finished, 'finished product' => ProductKind::Finished,
        'spare part' => ProductKind::SparePart, 'piece detachee' => ProductKind::SparePart, 'piece' => ProductKind::SparePart, 'part' => ProductKind::SparePart,
    ];

    /** Header written in the templates. */
    public function header(): string
    {
        return match ($this) {
            self::Code => 'Code',
            self::Name => 'Nom',
            self::Kind => 'Nature',
            self::Active => 'Actif',
            self::Category => 'Catégorie',
            self::Brand => 'Marque',
            self::Price => 'Prix HT',
            self::Stock => 'Stock',
            self::Range => 'Gamme',
            self::Model => 'Modèle',
            self::Typology => 'Typologie',
            self::OemReference => 'Référence constructeur',
            self::Power => 'Puissance (kW)',
            self::Refrigerant => 'Fluide frigorigène',
            self::Scop => 'SCOP',
            self::Capacity => 'Capacité (L)',
            self::Sectors => 'Secteurs',
            self::Documents => 'Documents',
            self::ShortDescription => 'Description courte',
            self::Description => 'Description',
        };
    }

    /** Field of the API feed. */
    public function path(): string
    {
        return match ($this) {
            self::Code => 'sku',
            self::Name => 'label',
            self::Kind => 'type',
            self::Active => 'status',
            self::Category => 'category',
            self::Brand => 'brand',
            self::Price => 'price.amount',
            self::Stock => 'stock',
            self::Range => 'range',
            self::Model => 'model',
            self::Typology => 'typology',
            self::OemReference => 'oem_reference',
            self::Power => 'specs.power_kw',
            self::Refrigerant => 'specs.refrigerant',
            self::Scop => 'specs.scop',
            self::Capacity => 'specs.capacity_l',
            self::Sectors => 'sectors',
            self::Documents => 'documents',
            self::ShortDescription => 'short_description',
            self::Description => 'description',
        };
    }

    /** A file without this column cannot be imported, a line with this cell empty is rejected. */
    public function required(): bool
    {
        return in_array($this, [self::Code, self::Name, self::Kind, self::Active, self::Category, self::Price, self::Stock], true);
    }

    /**
     * Headers recognised for this column, normalised (normalize()).
     *
     * @return list<string>
     */
    public function aliases(): array
    {
        return match ($this) {
            self::Code => ['code', 'sku', 'reference', 'ref', 'code article', 'code produit', 'product code', 'item code'],
            self::Name => ['nom', 'name', 'label', 'libelle', 'designation', 'intitule', 'product name'],
            self::Kind => ['nature', 'type', 'kind', 'type article', 'product type'],
            self::Active => ['actif', 'active', 'statut', 'status', 'enabled', 'etat', 'statut actif'],
            self::Category => ['categorie', 'category', 'code categorie', 'category code', 'taxon'],
            self::Brand => ['marque', 'brand'],
            self::Price => ['prix ht', 'prix', 'price', 'prix unitaire ht', 'unit price', 'price excl tax', 'net price', 'amount'],
            self::Stock => ['stock', 'quantite', 'quantity', 'qty', 'disponibilite', 'availability', 'stock disponible'],
            self::Range => ['gamme', 'range'],
            self::Model => ['modele', 'model'],
            self::Typology => ['typologie', 'typology'],
            self::OemReference => ['reference constructeur', 'oem reference', 'manufacturer reference', 'ref constructeur'],
            self::Power => ['puissance', 'puissance kw', 'power', 'power kw'],
            self::Refrigerant => ['fluide frigorigene', 'fluide', 'refrigerant'],
            self::Scop => ['scop'],
            self::Capacity => ['capacite', 'capacite l', 'capacity', 'capacity l'],
            self::Sectors => ['secteurs', 'sectors', 'secteur'],
            self::Documents => ['documents', 'documentation'],
            self::ShortDescription => ['description courte', 'short description', 'accroche'],
            self::Description => ['description', 'description longue', 'long description'],
        };
    }

    /** Value of the example line of the templates. */
    public function example(): string
    {
        return match ($this) {
            self::Code => 'SRK25ZS-W',
            self::Name => 'SRK25ZS-W Mural - 2,5kW - R32',
            self::Kind => 'Produit fini',
            self::Active => 'oui',
            self::Category => 'pac_air_air_residentiel_ui_mural',
            self::Brand => 'MHI',
            self::Price => '594,90',
            self::Stock => '12',
            self::Range => 'Pompe à Chaleur Air/Air',
            self::Model => 'SRK25',
            self::Typology => 'Mural',
            self::OemReference => '',
            self::Power => '2,5',
            self::Refrigerant => 'R32',
            self::Scop => '',
            self::Capacity => '',
            self::Sectors => 'Résidentiel, Petit Tertiaire',
            self::Documents => 'Notice d\'installation, Notice d\'utilisation',
            self::ShortDescription => 'Ligne esthétique conçue par un cabinet spécialisé.',
            self::Description => '',
        };
    }

    /** Header brought to the form of the aliases: brackets dropped, ASCII, lower case, words split by one space. */
    public static function normalize(string $header): string
    {
        $text = (string) preg_replace('/\([^)]*\)/u', ' ', $header);
        $text = (new UnicodeString($text))->ascii()->lower()->toString();

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $text));
    }

    public static function fromHeader(string $header): ?self
    {
        $normalized = self::normalize($header);
        foreach (self::cases() as $column) {
            if (in_array($normalized, $column->aliases(), true)) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Writes the cell into the record of the feed; an empty cell leaves the field out.
     *
     * @param array<string, mixed> $record
     *
     * @throws InvalidSourceProduct when the cell does not hold a value of the column
     */
    public function put(array &$record, string $cell): void
    {
        $cell = trim($cell);
        if ('' === $cell) {
            if ($this->required()) {
                throw new InvalidSourceProduct(sprintf('colonne « %s » vide.', $this->header()));
            }
            $this->write($record, null);

            return;
        }

        $this->write($record, match ($this) {
            self::Price => $this->decimal($cell),
            self::Power, self::Scop => (float) $this->decimal($cell),
            self::Stock, self::Capacity => $this->integer($cell),
            self::Active => $this->status($cell),
            self::Kind => $this->kind($cell),
            self::Sectors, self::Documents => $this->list($cell),
            default => $cell,
        });
    }

    /**
     * Sets the field of the column in the record, or removes it (null).
     *
     * @param array<string, mixed> $record
     */
    public function write(array &$record, mixed $value): void
    {
        [$field, $nestedField] = array_pad(explode('.', $this->path(), 2), 2, null);
        if (null === $nestedField) {
            if (null === $value) {
                unset($record[$field]);
            } else {
                $record[$field] = $value;
            }

            return;
        }

        $nested = $record[$field] ?? [];
        $nested = is_array($nested) ? $nested : [];
        if (null === $value) {
            unset($nested[$nestedField]);
        } else {
            $nested[$nestedField] = $value;
        }
        $record[$field] = $nested;
    }

    /**
     * Value of the record in this column, as an administrator types it (correction before a replay).
     *
     * @param array<mixed> $record
     */
    public function read(array $record): string
    {
        $path = explode('.', $this->path());
        $value = $record[$path[0]] ?? null;
        if (2 === count($path)) {
            $value = is_array($value) ? ($value[$path[1]] ?? null) : null;
        }

        return match (true) {
            null === $value => '',
            self::Active === $this => match ($value) {
                'active' => 'oui',
                'inactive' => 'non',
                default => is_string($value) ? $value : '',
            },
            self::Kind === $this => is_string($value) ? (ProductKind::tryFrom($value)?->label() ?? $value) : '',
            is_array($value) => implode(ProductReference::DOCUMENT_SEPARATOR, array_filter($value, is_string(...))),
            is_float($value), is_int($value) => str_replace('.', ',', (string) $value),
            is_string($value) && in_array($this, [self::Price, self::Power, self::Scop], true) => str_replace('.', ',', $value),
            is_scalar($value) => (string) $value,
            default => '',
        };
    }

    /** « 1 699,90 », « 1699.90 », « 1.699,90 », « 12 € » => « 1699.90 ». */
    private function decimal(string $cell): string
    {
        $text = str_replace([' ', "\u{a0}", "\u{202f}", '€', "'"], '', $cell);
        $comma = strrpos($text, ',');
        $dot = strrpos($text, '.');
        if (false !== $comma && false !== $dot) {
            $text = $comma > $dot ? str_replace(['.', ','], ['', '.'], $text) : str_replace(',', '', $text);
        } elseif (false !== $comma) {
            $text = str_replace(',', '.', $text);
        }
        if (1 !== preg_match('/^-?\d+(\.\d+)?$/', $text)) {
            throw new InvalidSourceProduct(sprintf('colonne « %s » : nombre attendu (« %s »).', $this->header(), $cell));
        }

        return $text;
    }

    private function integer(string $cell): int
    {
        $number = $this->decimal($cell);
        if ((float) $number !== floor((float) $number)) {
            throw new InvalidSourceProduct(sprintf('colonne « %s » : nombre entier attendu (« %s »).', $this->header(), $cell));
        }

        return (int) $number;
    }

    private function status(string $cell): string
    {
        $value = self::normalize($cell);

        return match (true) {
            in_array($value, self::TRUE_VALUES, true) => 'active',
            in_array($value, self::FALSE_VALUES, true) => 'inactive',
            default => throw new InvalidSourceProduct(sprintf('colonne « %s » : oui ou non attendu (« %s »).', $this->header(), $cell)),
        };
    }

    private function kind(string $cell): string
    {
        $kind = self::KIND_VALUES[self::normalize($cell)]
            ?? throw new InvalidSourceProduct(sprintf('colonne « %s » : « Produit fini » ou « Pièce détachée » attendu (« %s »).', $this->header(), $cell));

        return $kind->value;
    }

    /** @return list<string> */
    private function list(string $cell): array
    {
        return array_values(array_filter(array_map(trim(...), preg_split('/[,;|\n]/', $cell) ?: []), static fn (string $item): bool => '' !== $item));
    }
}
