<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Fixture;

use App\Entity\Catalog\SyncRun;
use App\Tests\Yack\Catalog\Application\InMemorySyncRuns;
use App\Yack\Catalog\Domain\FieldChange;
use App\Yack\Catalog\Domain\SyncSource;
use App\Yack\Catalog\Domain\SyncStatus;
use App\Yack\Catalog\Domain\SyncTrigger;
use App\Yack\Catalog\Infrastructure\Fixture\YackSyncHistoryFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * @internal
 */
#[CoversClass(YackSyncHistoryFixture::class)]
final class YackSyncHistoryFixtureTest extends TestCase
{
    private InMemorySyncRuns $runs;

    private YackSyncHistoryFixture $fixture;

    private string $feed;

    #[\Override]
    protected function setUp(): void
    {
        $this->runs = new InMemorySyncRuns();
        $this->fixture = new YackSyncHistoryFixture($this->runs);
        $this->feed = (string) tempnam(sys_get_temp_dir(), 'yack-feed');
    }

    #[\Override]
    protected function tearDown(): void
    {
        unlink($this->feed);
    }

    public function testThirtyDaysOfRunsTellTheScenarioOfTheDashboard(): void
    {
        $this->writeFeed([
            ['sku' => 'YK-1', 'label' => 'Console murale', 'price' => ['amount' => '594.90'], 'stock' => 12],
            ['sku' => 'YK-2', 'label' => 'Pompe à chaleur', 'price' => ['amount' => 1699], 'stock' => 'beaucoup'],
            ['sku' => 'YK-3', 'label' => 'Ballon', 'price' => ['amount' => '899.00']],
            ['sku' => 'YK-SANS-PRIX', 'label' => 'Sans prix'],
            'pas un objet',
        ]);

        $this->fixture->load(['feed' => $this->feed, 'alert_recipient' => 'catalogue@yack.test']);

        $runs = $this->runs->runs;
        $this->assertCount(25, $runs);
        $paris = new \DateTimeZone('Europe/Paris');
        $today = new \DateTimeImmutable('today', $paris);

        $failures = array_values(array_filter($runs, static fn (SyncRun $run): bool => SyncStatus::Failure === $run->getStatus()));
        $this->assertCount(1, $failures);
        $this->assertSame(['catalogue@yack.test', 30000, $today->modify('-17 days 06:00')->format('d/m/Y H:i')], [$failures[0]->getNotifiedTo(), $failures[0]->getDurationMs(), $failures[0]->getStartedAt()->setTimezone($paris)->format('d/m/Y H:i')]);

        $partials = array_values(array_filter($runs, static fn (SyncRun $run): bool => SyncStatus::Partial === $run->getStatus()));
        $this->assertSame(['Prix négatif : -1699.00 EUR.', 'Catégorie inconnue : « pac_air_eau_hybride ».'], array_map(static fn (SyncRun $run): ?string => $run->getLines()[5]->getReason(), $partials));
        $this->assertSame('-1699.00', $partials[0]->getLines()[5]->getPayload()['price']['amount'] ?? null);

        $replays = array_values(array_filter($runs, static fn (SyncRun $run): bool => SyncSource::Replay === $run->getSource()));
        $this->assertCount(1, $replays);
        $this->assertSame([$partials[1]->getId(), 'admin', sprintf('Rejets de la synchronisation n° %d', $partials[1]->getId())], [$replays[0]->getReplayOf(), $replays[0]->getOperator(), $replays[0]->getOrigin()]);

        $this->assertSame(1, count(array_filter($runs, static fn (SyncRun $run): bool => $run->isDryRun())));
        $this->assertSame(['tarifs-yack-novembre.csv'], array_values(array_map(static fn (SyncRun $run): string => $run->getOrigin(), array_filter($runs, static fn (SyncRun $run): bool => SyncSource::Csv === $run->getSource()))));
        $this->assertSame(4, count(array_filter($runs, static fn (SyncRun $run): bool => SyncTrigger::BackOffice === $run->getTrigger())));

        $last = array_values(array_filter($runs, static fn (SyncRun $run): bool => $run->getStartedAt()->setTimezone($paris)->format('d/m/Y H:i') === $today->modify('-1 day 06:00')->format('d/m/Y H:i')));
        $this->assertCount(1, $last);
        $this->assertSame(SyncStatus::Success, $last[0]->getStatus());

        $changes = [];
        foreach ($runs as $run) {
            foreach ($run->getLines() as $line) {
                foreach ($line->getChanges() as $change) {
                    $changes[$change->field] = $change;
                }
            }
        }
        $this->assertSame(['price', 'stock'], array_keys($changes));
        $this->assertInstanceOf(FieldChange::class, $changes['stock']);
    }

    public function testAFeedWithoutProductIsRefused(): void
    {
        $this->writeFeed([['sku' => 'YK-1']]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Yack sync history: no product in the feed "%s".', $this->feed));

        $this->fixture->load(['feed' => $this->feed, 'alert_recipient' => 'catalogue@yack.test']);
    }

    /** @param array<string, mixed> $options */
    #[DataProvider('provideInvalidOptions')]
    public function testOptionsThatAreNotStringsAreRefused(array $options): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Yack sync history: the "feed" and "alert_recipient" options must be strings.');

        $this->fixture->load($options);
    }

    /** @return \Iterator<string, array{array<string, mixed>}> */
    public static function provideInvalidOptions(): \Iterator
    {
        yield 'sans flux' => [['alert_recipient' => 'catalogue@yack.test']];
        yield 'sans destinataire' => [['feed' => 'data/yack/catalog.json']];
    }

    public function testTheFixtureNeedsAFeedAndARecipient(): void
    {
        $processor = new Processor();
        $tree = $this->fixture->getConfigTreeBuilder()->buildTree();

        $this->assertSame('yack_sync_history', $this->fixture->getName());
        $this->assertSame(['feed' => 'data/yack/catalog.json', 'alert_recipient' => 'catalogue@yack.test'], $processor->process($tree, [['feed' => 'data/yack/catalog.json', 'alert_recipient' => 'catalogue@yack.test']]));

        $this->expectException(InvalidConfigurationException::class);

        $processor->process($tree, [['feed' => 'data/yack/catalog.json']]);
    }

    /** @param list<mixed> $products */
    private function writeFeed(array $products): void
    {
        file_put_contents($this->feed, json_encode(['products' => $products], \JSON_THROW_ON_ERROR));
    }
}
