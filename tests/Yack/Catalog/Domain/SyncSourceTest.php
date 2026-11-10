<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain;

use App\Yack\Catalog\Domain\SyncSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SyncSource::class)]
final class SyncSourceTest extends TestCase
{
    #[DataProvider('provideSources')]
    public function testLabelAndManualImport(SyncSource $source, string $label, bool $manualImport): void
    {
        $this->assertSame([$label, $manualImport], [$source->label(), $source->isManualImport()]);
    }

    /** @return \Iterator<string, array{SyncSource, string, bool}> */
    public static function provideSources(): \Iterator
    {
        yield 'api' => [SyncSource::Api, 'API PIM/ERP', false];
        yield 'csv' => [SyncSource::Csv, 'Import CSV', true];
        yield 'xlsx' => [SyncSource::Xlsx, 'Import XLSX', true];
        yield 'rejeu' => [SyncSource::Replay, 'Rejeu des rejets', false];
    }
}
