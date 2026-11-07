<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Infrastructure\Library;

use App\Yack\Assistance\Domain\InvalidManual;
use App\Yack\Assistance\Domain\Manual;
use App\Yack\Assistance\Domain\ManualLibrary;
use App\Yack\Assistance\Domain\ManualProduct;
use App\Yack\Assistance\Domain\ManualType;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adapter : la base versionnée des manuels (index JSON produit hors du dépôt par le générateur), lue une
 * fois par instance, donc une fois par requête. Fichier absent : base vide, sans erreur. Un document
 * invalide arrête le chargement (`InvalidManual` avec son id) : la base est corrigée, pas filtrée en silence.
 */
#[AsAlias(ManualLibrary::class)]
final class JsonManualLibrary implements ManualLibrary
{
    /** @var array{documents: list<Manual>, products: list<ManualProduct>}|null */
    private ?array $loaded = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/data/yack/assistance/manuels.json')]
        private readonly string $path,
    ) {
    }

    public function documents(): array
    {
        return $this->load()['documents'];
    }

    public function products(): array
    {
        return $this->load()['products'];
    }

    /** @return array{documents: list<Manual>, products: list<ManualProduct>} */
    private function load(): array
    {
        if (null !== $this->loaded) {
            return $this->loaded;
        }
        if (!is_file($this->path)) {
            return $this->loaded = ['documents' => [], 'products' => []];
        }

        try {
            $payload = json_decode((string) file_get_contents($this->path), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw InvalidManual::library(sprintf('JSON invalide (%s)', $exception->getMessage()));
        }
        if (!\is_array($payload) || !\is_array($payload['documents'] ?? null)) {
            throw InvalidManual::library('liste « documents » absente');
        }

        $documents = [];
        foreach (array_values($payload['documents']) as $index => $record) {
            $document = $this->document(\is_array($record) ? $record : [], $index);
            if (isset($documents[$document->id])) {
                throw InvalidManual::document($document->id, 'identifiant en double');
            }
            $documents[$document->id] = $document;
        }

        $products = [];
        foreach (\is_array($payload['products'] ?? null) ? array_values($payload['products']) : [] as $record) {
            $products[] = $this->product(\is_array($record) ? $record : []);
        }

        return $this->loaded = ['documents' => array_values($documents), 'products' => $products];
    }

    /** @param array<mixed> $record */
    private function document(array $record, int $index): Manual
    {
        $id = \is_string($record['id'] ?? null) ? $record['id'] : sprintf('#%d', $index);
        $type = ManualType::tryFrom(self::optionalString($record, 'type') ?? '')
            ?? throw InvalidManual::document($id, 'type inconnu');
        $title = self::optionalString($record, 'title') ?? throw InvalidManual::document($id, 'titre absent');
        $url = self::optionalString($record, 'url') ?? throw InvalidManual::document($id, 'lien absent');
        $year = $record['year'] ?? null;

        return new Manual(
            id: $id,
            title: $title,
            type: $type,
            languages: self::strings($record, 'languages'),
            url: $url,
            official: true === ($record['official'] ?? null),
            models: self::strings($record, 'models'),
            catalogCodes: self::strings($record, 'catalogCodes'),
            brand: self::optionalString($record, 'brand'),
            manualNumber: self::optionalString($record, 'manualNumber'),
            year: \is_int($year) ? (string) $year : self::optionalString($record, 'year'),
            pages: \is_int($record['pages'] ?? null) ? $record['pages'] : null,
            text: self::optionalString($record, 'text'),
            indexedPages: self::optionalString($record, 'indexedPages'),
        );
    }

    /** @param array<mixed> $record */
    private function product(array $record): ManualProduct
    {
        return new ManualProduct(
            code: self::optionalString($record, 'code') ?? '',
            name: self::optionalString($record, 'name') ?? '',
            brand: self::optionalString($record, 'brand'),
            family: self::strings($record, 'family'),
        );
    }

    /** @param array<mixed> $record */
    private static function optionalString(array $record, string $key): ?string
    {
        $value = $record[$key] ?? null;

        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    /**
     * @param array<mixed> $record
     *
     * @return list<string>
     */
    private static function strings(array $record, string $key): array
    {
        $values = $record[$key] ?? null;

        return \is_array($values) ? array_values(array_filter($values, static fn (mixed $value): bool => \is_string($value) && '' !== trim($value))) : [];
    }
}
