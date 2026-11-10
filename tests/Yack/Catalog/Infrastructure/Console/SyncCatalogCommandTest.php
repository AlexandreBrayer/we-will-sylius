<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Console;

use App\Yack\Catalog\Application\SynchronizeCatalog;
use App\Yack\Catalog\Domain\ProductChange;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SyncOutcome;
use App\Yack\Catalog\Domain\SyncReport;
use App\Yack\Catalog\Infrastructure\Console\SyncCatalogCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * @internal
 */
#[CoversClass(SyncCatalogCommand::class)]
final class SyncCatalogCommandTest extends TestCase
{
    /** @var list<object> */
    private array $dispatched = [];

    public function testTheDefaultFeedIsSynchronisedAndTheCountersShown(): void
    {
        $report = new SyncReport('data/yack/catalog.json');
        $report->record(new ProductChange('YK-1', 'Produit 1', SyncOutcome::Created));
        $report->recordedAs(12);
        $tester = $this->tester($report);

        $this->assertSame(Command::SUCCESS, $tester->execute([]));

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Synchronisation catalogue Yack : data/yack/catalog.json', $display);
        $this->assertStringContainsString('Journal : synchronisation n° 12', $display);
        $this->assertStringContainsString('Flux data/yack/catalog.json synchronisé.', $display);
        $this->assertCount(1, $this->dispatched);
        $message = $this->dispatched[0];
        $this->assertInstanceOf(SynchronizeCatalog::class, $message);
        $this->assertSame(['data/yack/catalog.json', false, true], [$message->feed, $message->dryRun, $message->recorded]);
    }

    public function testASimulationOfAnotherFeedWritesNothing(): void
    {
        $tester = $this->tester(new SyncReport('data/yack/delta.json', dryRun: true));

        $this->assertSame(Command::SUCCESS, $tester->execute(['feed' => 'data/yack/delta.json', '--dry-run' => true]));

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Simulation de synchronisation catalogue Yack : data/yack/delta.json', $display);
        $this->assertStringContainsString('Flux data/yack/delta.json simulé : rien n\'a été écrit.', $display);
        $this->assertStringNotContainsString('Journal', $display);
        $message = $this->dispatched[0];
        $this->assertInstanceOf(SynchronizeCatalog::class, $message);
        $this->assertTrue($message->dryRun);
    }

    public function testRejectedRecordsAreListedAndFailTheCommand(): void
    {
        $report = new SyncReport('data/yack/catalog.json');
        $report->fail(new RejectedRecord('YK-BAD', 'Prix absent.'));
        $tester = $this->tester($report);

        $this->assertSame(Command::FAILURE, $tester->execute([]));

        $this->assertStringContainsString('YK-BAD : Prix absent.', $tester->getDisplay());
    }

    public function testAnUnavailableSourceFailsTheCommandWithTheRunOfTheJournal(): void
    {
        $report = new SyncReport('data/yack/catalog.json');
        $report->sourceFailed('Flux injoignable.');
        $report->recordedAs(7);
        $tester = $this->tester($report);

        $this->assertSame(Command::FAILURE, $tester->execute([]));

        $this->assertStringContainsString('Flux injoignable. (journal : synchronisation n° 7).', $tester->getDisplay());
    }

    public function testNoReportFailsTheCommand(): void
    {
        $tester = $this->tester(null);

        $this->assertSame(Command::FAILURE, $tester->execute([]));

        $this->assertStringContainsString('La synchronisation n\'a renvoyé aucun rapport.', $tester->getDisplay());
    }

    public function testAnEmptyFeedIsInvalid(): void
    {
        $tester = $this->tester(null);

        $this->assertSame(Command::INVALID, $tester->execute(['feed' => '']));

        $this->assertStringContainsString('Flux Yack manquant.', $tester->getDisplay());
        $this->assertSame([], $this->dispatched);
    }

    private function tester(?SyncReport $report): CommandTester
    {
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $message) use ($report): Envelope {
            $this->dispatched[] = $message;
            $envelope = new Envelope($message);

            return null === $report ? $envelope : $envelope->with(new HandledStamp($report, 'handler'));
        });

        return new CommandTester(new SyncCatalogCommand($bus, 'data/yack/catalog.json'));
    }
}
