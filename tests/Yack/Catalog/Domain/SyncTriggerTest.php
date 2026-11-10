<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain;

use App\Yack\Catalog\Domain\SyncTrigger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SyncTrigger::class)]
final class SyncTriggerTest extends TestCase
{
    public function testEachTriggerHasItsLabel(): void
    {
        $this->assertSame(['Ligne de commande', 'Back-office'], array_map(static fn (SyncTrigger $trigger): string => $trigger->label(), SyncTrigger::cases()));
    }
}
