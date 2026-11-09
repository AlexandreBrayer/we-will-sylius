<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Yack\Content\Domain\CmsEditLocked;
use App\Yack\Content\Domain\CmsEditLockState;
use App\Yack\Content\Domain\CmsRevisionAuthor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CmsEditLocked::class)]
final class CmsEditLockedTest extends TestCase
{
    public function testTheRefusalNamesTheHolderAndTheContent(): void
    {
        $lock = new CmsEditLockState(1, new CmsRevisionAuthor('Sophie', 'Martin', 'Sophie Martin'), str_repeat('c', 32), new \DateTimeImmutable(), new \DateTimeImmutable(), 1);

        $this->assertSame(
            'Sophie Martin modifie cette page en ce moment : votre enregistrement est refusé pour ne pas écraser son travail. Rouvrez le builder pour voir sa version, ou prenez la main.',
            (new CmsEditLocked($lock, false))->getMessage(),
        );
        $this->assertStringStartsWith('Sophie Martin modifie ce template en ce moment', (new CmsEditLocked($lock, true))->getMessage());
        $this->assertSame($lock, (new CmsEditLocked($lock, true))->lock);
    }
}
