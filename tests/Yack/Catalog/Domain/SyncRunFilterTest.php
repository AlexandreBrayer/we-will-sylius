<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain;

use App\Yack\Catalog\Domain\SyncRunFilter;
use App\Yack\Catalog\Domain\SyncSource;
use App\Yack\Catalog\Domain\SyncStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SyncRunFilter::class)]
final class SyncRunFilterTest extends TestCase
{
    public function testTheQueryOfTheListIsReadAndWrittenBack(): void
    {
        $filter = SyncRunFilter::fromQuery('partial', 'csv', '7');

        $this->assertSame([SyncStatus::Partial, SyncSource::Csv, false, 7, false], [$filter->status, $filter->source, $filter->simulationsOnly, $filter->days, $filter->isEmpty()]);
        $this->assertSame(['statut' => 'partial', 'source' => 'csv', 'periode' => '7'], $filter->toQuery());
        $this->assertSame('2026-11-03 06:00', $filter->since(new \DateTimeImmutable('2026-11-10 06:00'))?->format('Y-m-d H:i'));
    }

    public function testTheSimulationsAreAPseudoSource(): void
    {
        $filter = SyncRunFilter::fromQuery('', 'simulation', '');

        $this->assertSame([null, null, true, null, false], [$filter->status, $filter->source, $filter->simulationsOnly, $filter->days, $filter->isEmpty()]);
        $this->assertSame(['source' => 'simulation'], $filter->toQuery());
    }

    public function testAValueTheListDoesNotKnowIsIgnored(): void
    {
        $filter = SyncRunFilter::fromQuery('perdu', 'ftp', '12');

        $this->assertTrue($filter->isEmpty());
        $this->assertSame([], $filter->toQuery());
        $this->assertNull($filter->since(new \DateTimeImmutable()));
        $this->assertNull(SyncRunFilter::fromQuery('', '', 'semaine')->days);
    }
}
