<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain;

use App\Yack\Catalog\Domain\RejectedRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RejectedRecord::class)]
final class RejectedRecordTest extends TestCase
{
    /** @param array<string, mixed> $payload */
    #[DataProvider('providePayloads')]
    public function testTheNameOfTheProductIsReadInTheRecord(array $payload, ?string $name): void
    {
        $record = new RejectedRecord('YK-1', 'Prix absent.', $payload, 4);

        $this->assertSame([$name, 4], [$record->name(), $record->line]);
    }

    /** @return \Iterator<string, array{array<string, mixed>, string|null}> */
    public static function providePayloads(): \Iterator
    {
        yield 'libellé' => [['label' => ' Console murale '], 'Console murale'];
        yield 'libellé vide' => [['label' => '  '], null];
        yield 'libellé qui n\'est pas un texte' => [['label' => 12], null];
        yield 'aucun enregistrement' => [[], null];
    }
}
