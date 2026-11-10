<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Fixture;

use App\Yack\Catalog\Application\SynchronizeCatalog;
use App\Yack\Catalog\Domain\ProductChange;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SyncOutcome;
use App\Yack\Catalog\Domain\SyncReport;
use App\Yack\Catalog\Infrastructure\Fixture\YackCatalogFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * @internal
 */
#[CoversClass(YackCatalogFixture::class)]
final class YackCatalogFixtureTest extends TestCase
{
    /** @var list<object> */
    private array $dispatched = [];

    public function testTheDemoCatalogIsTheFirstExportLeftOutOfTheJournal(): void
    {
        $report = new SyncReport('data/yack/catalog.json');
        $report->record(new ProductChange('YK-1', 'Produit 1', SyncOutcome::Created));

        $this->fixture($report)->load(['feed' => 'data/yack/catalog.json']);

        $message = $this->dispatched[0] ?? null;
        $this->assertInstanceOf(SynchronizeCatalog::class, $message);
        $this->assertSame(['data/yack/catalog.json', false, false], [$message->feed, $message->dryRun, $message->recorded]);
    }

    #[DataProvider('provideFailedSeeds')]
    public function testAFailedSeedStopsTheFixtures(?SyncReport $report, string $message): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($message);

        $this->fixture($report)->load(['feed' => 'data/yack/catalog.json']);
    }

    /** @return \Iterator<string, array{SyncReport|null, string}> */
    public static function provideFailedSeeds(): \Iterator
    {
        $unavailable = new SyncReport('data/yack/catalog.json');
        $unavailable->sourceFailed('Flux illisible.');
        $rejected = new SyncReport('data/yack/catalog.json');
        $rejected->fail(new RejectedRecord('YK-1', 'Prix absent.'));
        $rejected->fail(new RejectedRecord('YK-2', 'Stock négatif.'));

        yield 'aucun rapport' => [null, 'Yack catalog seed "data/yack/catalog.json" failed: no report'];
        yield 'source indisponible' => [$unavailable, 'Yack catalog seed "data/yack/catalog.json" failed: Flux illisible.'];
        yield 'enregistrements rejetés' => [$rejected, 'Yack catalog seed "data/yack/catalog.json" failed: YK-1: Prix absent. | YK-2: Stock négatif.'];
    }

    public function testAFeedThatIsNotAPathIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Yack catalog seed: the "feed" option must be a path.');

        $this->fixture(null)->load([]);
    }

    public function testTheFixtureNeedsAFeed(): void
    {
        $fixture = $this->fixture(null);
        $processor = new Processor();
        $tree = $fixture->getConfigTreeBuilder()->buildTree();

        $this->assertSame('yack_catalog', $fixture->getName());
        $this->assertSame(['feed' => 'data/yack/catalog.json'], $processor->process($tree, [['feed' => 'data/yack/catalog.json']]));

        $this->expectException(InvalidConfigurationException::class);

        $processor->process($tree, [[]]);
    }

    private function fixture(?SyncReport $report): YackCatalogFixture
    {
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $message) use ($report): Envelope {
            $this->dispatched[] = $message;
            $envelope = new Envelope($message);

            return null === $report ? $envelope : $envelope->with(new HandledStamp($report, 'handler'));
        });

        return new YackCatalogFixture($bus);
    }
}
