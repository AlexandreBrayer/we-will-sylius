<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Persistence;

use App\Yack\Content\Infrastructure\Persistence\DoctrineCmsContentTransaction;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DoctrineCmsContentTransaction::class)]
final class DoctrineCmsContentTransactionTest extends TestCase
{
    public function testTheWorkRunsInsideOneDoctrineTransactionAndItsResultComesBack(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('wrapInTransaction')
            ->willReturnCallback(static fn (callable $work): mixed => $work($entityManager));
        $calls = 0;

        $result = (new DoctrineCmsContentTransaction($entityManager))->run(static function () use (&$calls): string {
            ++$calls;

            return 'fait';
        });

        $this->assertSame('fait', $result);
        $this->assertSame(1, $calls);
    }
}
