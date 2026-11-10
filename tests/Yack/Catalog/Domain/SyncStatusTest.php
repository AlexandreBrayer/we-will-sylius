<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain;

use App\Yack\Catalog\Domain\SyncStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SyncStatus::class)]
final class SyncStatusTest extends TestCase
{
    public function testEachStatusHasItsLabel(): void
    {
        $this->assertSame(['Succès', 'Partiel', 'Échec'], array_map(static fn (SyncStatus $status): string => $status->label(), SyncStatus::cases()));
    }
}
