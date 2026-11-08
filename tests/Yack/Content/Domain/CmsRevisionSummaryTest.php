<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Yack\Content\Domain\CmsPageStatus;
use App\Yack\Content\Domain\CmsRevisionAuthor;
use App\Yack\Content\Domain\CmsRevisionSummary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CmsRevisionSummary::class)]
final class CmsRevisionSummaryTest extends TestCase
{
    public function testItCarriesWhatTheHistoryListShows(): void
    {
        $date = new \DateTimeImmutable('2026-11-08 14:05:00');
        $author = new CmsRevisionAuthor('Sophie', 'Martin', 'Sophie Martin');

        $summary = new CmsRevisionSummary(7, $date, $author, CmsPageStatus::Published, 'Accueil', 9);

        $this->assertSame(7, $summary->id);
        $this->assertSame($date, $summary->createdAt);
        $this->assertSame($author, $summary->author);
        $this->assertSame(CmsPageStatus::Published, $summary->status);
        $this->assertSame('Accueil', $summary->title);
        $this->assertSame(9, $summary->blockCount);
    }
}
