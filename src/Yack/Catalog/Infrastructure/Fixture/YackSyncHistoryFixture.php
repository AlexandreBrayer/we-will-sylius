<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Fixture;

use App\Entity\Catalog\SyncRun;
use App\Yack\Catalog\Domain\FieldChange;
use App\Yack\Catalog\Domain\ProductChange;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SyncContext;
use App\Yack\Catalog\Domain\SyncOutcome;
use App\Yack\Catalog\Domain\SyncReport;
use App\Yack\Catalog\Domain\SyncRuns;
use App\Yack\Catalog\Domain\SyncSource;
use App\Yack\Catalog\Domain\SyncTrigger;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/**
 * History of the dashboard of the synchronisations: about twenty runs over the last thirty days, built
 * from the products of the demo feed (deterministic). Mostly successful nightly deltas, a few partial
 * runs with rejected records, one API outage (alert sent) followed by a manual retry, one simulation,
 * one manual CSV import and one replay of rejects. Journal only: the catalog itself is not modified.
 */
final class YackSyncHistoryFixture extends AbstractFixture
{
    private const OPERATOR = 'admin';

    private const TIME_ZONE = 'Europe/Paris';

    /** @var list<array{code: string, name: string, cents: int, stock: int}> */
    private array $products = [];

    public function __construct(private readonly SyncRuns $runs)
    {
    }

    public function getName(): string
    {
        return 'yack_sync_history';
    }

    public function load(array $options): void
    {
        $feed = $options['feed'] ?? null;
        $recipient = $options['alert_recipient'] ?? null;
        if (!is_string($feed) || !is_string($recipient)) {
            throw new \InvalidArgumentException('Yack sync history: the "feed" and "alert_recipient" options must be strings.');
        }
        $this->products = self::products($feed);

        // Times of day in Paris (the back-office shows them so), stored in the default time zone like any run.
        $today = new \DateTimeImmutable('today', new \DateTimeZone(self::TIME_ZONE));
        $day = static fn (int $daysAgo, string $time = '06:00'): \DateTimeImmutable => $today
            ->modify(sprintf('-%d days %s', $daysAgo, $time))
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()));

        $replayed = null;
        foreach (range(29, 1) as $index => $daysAgo) {
            // A night without a delta now and then; never on the days of the scenario, nor the last night.
            if (1 === $index % 3 && !in_array($daysAgo, [24, 17, 13, 1], true)) {
                continue;
            }
            $startedAt = $day($daysAgo);
            $context = new SyncContext();

            if (17 === $daysAgo) {
                $this->runs->add($this->failed($context, $startedAt, $recipient));
                $this->add($this->delta($index, 4, self::origin($startedAt)), new SyncContext(trigger: SyncTrigger::BackOffice, operator: self::OPERATOR), $day($daysAgo, '09:42'), 2140);

                continue;
            }
            if (in_array($daysAgo, [24, 13], true)) {
                $report = $this->delta($index, 5, self::origin($startedAt));
                $report->fail($this->rejection($index, 24 === $daysAgo));
                $partial = $this->add($report, $context, $startedAt, 1960);
                if (13 === $daysAgo) {
                    $replayed = $partial;
                }

                continue;
            }

            $this->add($this->delta($index, 3 + $index % 5, self::origin($startedAt)), $context, $startedAt, 1200 + 90 * ($index % 7));
        }

        $this->add($this->delta(40, 3, self::origin($day(11)), dryRun: true), new SyncContext(trigger: SyncTrigger::BackOffice, operator: self::OPERATOR, dryRun: true), $day(11, '14:05'), 840);
        $this->add($this->delta(50, 6, 'tarifs-yack-novembre.csv'), new SyncContext(SyncSource::Csv, SyncTrigger::BackOffice, self::OPERATOR), $day(6, '16:20'), 610);
        if (null !== $replayed && null !== $replayed->getId()) {
            $this->add($this->delta(60, 1, sprintf('Rejets de la synchronisation n° %d', $replayed->getId())), new SyncContext(SyncSource::Replay, SyncTrigger::BackOffice, self::OPERATOR, replayOf: $replayed->getId()), $day(12, '10:15'), 95);
        }
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->scalarNode('feed')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('alert_recipient')->isRequired()->cannotBeEmpty()->end()
            ->end()
        ;
    }

    private function add(SyncReport $report, SyncContext $context, \DateTimeImmutable $startedAt, int $durationMs): SyncRun
    {
        $run = SyncRun::completed($report, $context, $startedAt, $startedAt->modify(sprintf('+%d milliseconds', $durationMs)));
        $this->runs->add($run);

        return $run;
    }

    private function failed(SyncContext $context, \DateTimeImmutable $startedAt, string $recipient): SyncRun
    {
        $run = SyncRun::failed($context, self::origin($startedAt), 'API Yack PIM/ERP injoignable : délai de réponse dépassé après 30 s.', $startedAt, $startedAt->modify('+30 seconds'));
        $run->notified($recipient);

        return $run;
    }

    /** Nightly delta of the PIM/ERP: price and stock changes on a few products, one of them unchanged. */
    private function delta(int $seed, int $size, string $origin, bool $dryRun = false): SyncReport
    {
        $report = new SyncReport($origin, $dryRun);
        for ($i = 0; $i < $size; ++$i) {
            $product = $this->products[($seed * 7 + $i * 13) % count($this->products)];
            $field = match (($seed + $i) % 3) {
                0 => new FieldChange('price', 'Prix HT', ProductChange::price($product['cents']), ProductChange::price($product['cents'] + 1000 * (1 + $i % 4))),
                1 => new FieldChange('stock', 'Stock', (string) $product['stock'], (string) ($product['stock'] + 5 + $i)),
                default => null,
            };
            $report->record(new ProductChange($product['code'], $product['name'], null === $field ? SyncOutcome::Unchanged : SyncOutcome::Updated, null === $field ? [] : [$field]));
        }

        return $report;
    }

    private function rejection(int $seed, bool $negativePrice): RejectedRecord
    {
        $product = $this->products[($seed * 11) % count($this->products)];
        $payload = ['sku' => $product['code'], 'label' => $product['name'], 'status' => 'active', 'price' => ['amount' => sprintf('%.2F', $product['cents'] / 100), 'currency' => 'EUR'], 'stock' => $product['stock']];

        return $negativePrice
            ? new RejectedRecord($product['code'], 'Prix négatif : -' . sprintf('%.2F', $product['cents'] / 100) . ' EUR.', ['price' => ['amount' => '-' . $payload['price']['amount'], 'currency' => 'EUR']] + $payload)
            : new RejectedRecord($product['code'], 'Catégorie inconnue : « pac_air_eau_hybride ».', ['category' => 'pac_air_eau_hybride'] + $payload);
    }

    private static function origin(\DateTimeImmutable $at): string
    {
        return sprintf('API Yack PIM/ERP, export du %s', $at->format('d/m/Y'));
    }

    /** @return list<array{code: string, name: string, cents: int, stock: int}> */
    private static function products(string $feed): array
    {
        $data = json_decode((string) @file_get_contents($feed), true);
        $products = [];
        foreach (is_array($data) && is_array($data['products'] ?? null) ? $data['products'] : [] as $record) {
            if (is_array($record) && is_string($record['sku'] ?? null) && is_string($record['label'] ?? null) && is_array($record['price'] ?? null) && is_numeric($record['price']['amount'] ?? null)) {
                $products[] = ['code' => $record['sku'], 'name' => $record['label'], 'cents' => (int) round(100 * (float) $record['price']['amount']), 'stock' => is_int($record['stock'] ?? null) ? $record['stock'] : 0];
            }
        }
        if ([] === $products) {
            throw new \InvalidArgumentException(sprintf('Yack sync history: no product in the feed "%s".', $feed));
        }

        return $products;
    }
}
