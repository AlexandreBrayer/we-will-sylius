<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Source;

use App\Yack\Catalog\Domain\InvalidSourceProduct;
use App\Yack\Catalog\Domain\ProductFeed;
use App\Yack\Catalog\Domain\ProductRecordMapper;
use App\Yack\Catalog\Domain\ProductSource;
use App\Yack\Catalog\Domain\ProductSourceHealth;
use App\Yack\Catalog\Domain\ProductSourceUnavailable;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SourceHealth;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adapter: reads a JSON export shaped like the Yack PIM/ERP API (see data/yack/*.json), each record
 * translated by the shared mapping (FeedRecordMapper) and kept as received for a later replay. The
 * outage of the API can be simulated from the back-office (SimulatedSourceOutage).
 *
 * Swapping to the real API = a new ProductSource implementation (HTTP client + same mapping).
 */
#[AsAlias(ProductSource::class)]
#[AsAlias(ProductSourceHealth::class)]
final readonly class JsonFileProductSource implements ProductSource, ProductSourceHealth
{
    public const SIMULATED_OUTAGE = 'API Yack PIM/ERP injoignable : délai de réponse dépassé (panne simulée depuis le back-office).';

    public function __construct(
        private ProductRecordMapper $mapper,
        private SimulatedSourceOutage $outage,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        #[Autowire('%app.yack.catalog_feed%')]
        private string $defaultFeed,
    ) {
    }

    public function fetch(string $feed): ProductFeed
    {
        $payload = $this->read($feed);
        $path = $this->path($feed);
        $records = is_array($payload) ? ($payload['products'] ?? null) : null;
        if (!is_array($records)) {
            throw new ProductSourceUnavailable(sprintf('Flux Yack « %s » : liste « products » absente.', $feed));
        }

        $products = [];
        $rejected = [];
        $payloads = [];
        foreach (array_values($records) as $index => $record) {
            $record = is_array($record) ? $record : [];
            $reference = SourceRecord::from($record)->optionalString('sku') ?? sprintf('#%d', $index + 1);

            try {
                $products[] = $this->mapper->map($record, dirname($path));
                $payloads[$reference] = $record;
            } catch (InvalidSourceProduct $exception) {
                $rejected[] = new RejectedRecord($reference, $exception->getMessage(), $record);
            }
        }

        return new ProductFeed(self::origin($path, $payload), $products, $rejected, $payloads);
    }

    public function check(): SourceHealth
    {
        if ($this->outage->isOn()) {
            return new SourceHealth(false, self::SIMULATED_OUTAGE, true);
        }

        try {
            $payload = $this->read($this->defaultFeed);
        } catch (ProductSourceUnavailable $exception) {
            return new SourceHealth(false, $exception->getMessage());
        }
        $records = is_array($payload) ? ($payload['products'] ?? null) : null;

        return new SourceHealth(true, sprintf(
            'Flux %s joignable : %d enregistrements disponibles.',
            self::origin($this->path($this->defaultFeed), $payload),
            is_array($records) ? count($records) : 0,
        ));
    }

    /** @throws ProductSourceUnavailable */
    private function read(string $feed): mixed
    {
        if ($this->outage->isOn()) {
            throw new ProductSourceUnavailable(self::SIMULATED_OUTAGE);
        }
        $path = $this->path($feed);
        if (!is_readable($path)) {
            throw new ProductSourceUnavailable(sprintf('Flux Yack « %s » illisible.', $feed));
        }

        try {
            return json_decode((string) file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ProductSourceUnavailable(sprintf('Flux Yack « %s » : JSON invalide (%s).', $feed, $exception->getMessage()), 0, $exception);
        }
    }

    private function path(string $feed): string
    {
        return str_starts_with($feed, '/') ? $feed : $this->projectDir . '/' . $feed;
    }

    private static function origin(string $path, mixed $payload): string
    {
        return sprintf('%s (%s)', basename($path), SourceRecord::from($payload)->optionalString('exportedAt') ?? 'n/a');
    }
}
