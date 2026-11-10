<?php

declare(strict_types=1);

namespace App\Tests\Entity\Catalog;

use App\Entity\Catalog\SyncRun;
use App\Entity\Catalog\SyncRunLine;
use App\Yack\Catalog\Domain\FieldChange;
use App\Yack\Catalog\Domain\ProductChange;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SyncContext;
use App\Yack\Catalog\Domain\SyncOutcome;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SyncRunLine::class)]
final class SyncRunLineTest extends TestCase
{
    public function testAChangeKeepsItsFieldLevelDifferences(): void
    {
        $run = self::failedRun();
        $field = new FieldChange('price', 'Prix HT', "590,00\u{a0}€", "649,00\u{a0}€");

        $line = SyncRunLine::change($run, 2, new ProductChange('YK-1', str_repeat('n', 300), SyncOutcome::Updated, [$field]));

        $this->assertSame([null, $run, 2, 'YK-1', 255, SyncOutcome::Updated, null, null, null, null], [$line->getId(), $line->getRun(), $line->getPosition(), $line->getCode(), mb_strlen((string) $line->getName()), $line->getOutcome(), $line->getReason(), $line->getPayload(), $line->payloadJson(), $line->getFileLine()]);
        $this->assertSame([$field->toArray()], array_map(static fn (FieldChange $change): array => $change->toArray(), $line->getChanges()));
    }

    public function testARejectionKeepsTheReasonAndTheRecordAsReceived(): void
    {
        $line = SyncRunLine::rejection(self::failedRun(), 1, new RejectedRecord(str_repeat('c', 300), 'Prix négatif.', ['sku' => 'YK-1', 'label' => 'Pompe à chaleur', 'image' => 'a/b.png'], 7));

        $this->assertSame([255, 'Pompe à chaleur', SyncOutcome::Failed, 'Prix négatif.', 7, []], [mb_strlen($line->getCode()), $line->getName(), $line->getOutcome(), $line->getReason(), $line->getFileLine(), $line->getChanges()]);
        $this->assertSame(['sku' => 'YK-1', 'label' => 'Pompe à chaleur', 'image' => 'a/b.png'], $line->getPayload());
        $this->assertSame("{\n    \"sku\": \"YK-1\",\n    \"label\": \"Pompe à chaleur\",\n    \"image\": \"a/b.png\"\n}", $line->payloadJson());
    }

    public function testARecordThatWasNotEvenAnObjectKeepsNoPayload(): void
    {
        $line = SyncRunLine::rejection(self::failedRun(), 1, new RejectedRecord('#2', 'Champ « sku » absent ou vide.'));

        $this->assertSame([null, null, null, null], [$line->getName(), $line->getPayload(), $line->payloadJson(), $line->getFileLine()]);
    }

    private static function failedRun(): SyncRun
    {
        return SyncRun::failed(new SyncContext(), 'catalog.json', 'API injoignable.', new \DateTimeImmutable('2026-11-10 06:00'), new \DateTimeImmutable('2026-11-10 06:00'));
    }
}
