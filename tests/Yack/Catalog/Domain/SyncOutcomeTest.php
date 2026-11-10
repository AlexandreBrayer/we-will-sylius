<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain;

use App\Yack\Catalog\Domain\SyncOutcome;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SyncOutcome::class)]
final class SyncOutcomeTest extends TestCase
{
    #[DataProvider('provideOutcomes')]
    public function testLabelsAndWhetherTheRecordWritesTheCatalog(SyncOutcome $outcome, string $label, string $plural, bool $writes): void
    {
        $this->assertSame([$label, $plural, $writes], [$outcome->label(), $outcome->plural(), $outcome->writes()]);
    }

    public function testTheLabelAgreesWithTheCounterSingularUpToOne(): void
    {
        $this->assertSame(
            ['Rejeté', 'Rejeté', 'Rejetés'],
            [SyncOutcome::Failed->labelFor(0), SyncOutcome::Failed->labelFor(1), SyncOutcome::Failed->labelFor(2)],
        );
    }

    /** @return \Iterator<string, array{SyncOutcome, string, string, bool}> */
    public static function provideOutcomes(): \Iterator
    {
        yield 'créé' => [SyncOutcome::Created, 'Créé', 'Créés', true];
        yield 'mis à jour' => [SyncOutcome::Updated, 'Mis à jour', 'Mis à jour', true];
        yield 'désactivé' => [SyncOutcome::Disabled, 'Désactivé', 'Désactivés', true];
        yield 'inchangé' => [SyncOutcome::Unchanged, 'Inchangé', 'Inchangés', false];
        yield 'rejeté' => [SyncOutcome::Failed, 'Rejeté', 'Rejetés', false];
    }
}
