<?php

declare(strict_types=1);

namespace App\Tests\Yack\Assistance\Domain;

use App\Yack\Assistance\Domain\Manual;
use App\Yack\Assistance\Domain\ManualAnswer;
use App\Yack\Assistance\Domain\ManualType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ManualAnswer::class)]
final class ManualAnswerTest extends TestCase
{
    public function testACitedAnswerKeepsEachPageOnce(): void
    {
        $manual = self::manual();

        $answer = ManualAnswer::cited('  Lavez le filtre.  ', [['manual' => $manual, 'page' => 33], ['manual' => $manual, 'page' => 33], ['manual' => $manual, 'page' => 34]], simulated: true);

        $this->assertSame('Lavez le filtre.', $answer->text);
        $this->assertSame([33, 34], array_map(static fn (array $citation): int => $citation['page'], $answer->citations));
        $this->assertTrue($answer->simulated);
        $this->assertFalse($answer->fromTable);
        $this->assertTrue($answer->isFound());
    }

    public function testAnAnswerFromATableCarriesTheWarning(): void
    {
        $answer = ManualAnswer::cited('E16 : moteur du ventilateur intérieur.', [['manual' => self::manual(), 'page' => 82]], fromTable: true);

        $this->assertSame('E16 : moteur du ventilateur intérieur. ' . ManualAnswer::TABLE_WARNING, $answer->text);
        $this->assertTrue($answer->fromTable);
    }

    public function testNotFoundAndNotIndexedHaveNoSource(): void
    {
        $this->assertSame([ManualAnswer::NOT_FOUND, [], true], [ManualAnswer::notFound(true)->text, ManualAnswer::notFound(true)->citations, ManualAnswer::notFound(true)->simulated]);
        $this->assertSame([ManualAnswer::NOT_INDEXED, []], [ManualAnswer::notIndexed()->text, ManualAnswer::notIndexed()->citations]);
        $this->assertFalse(ManualAnswer::notIndexed()->isFound());
        $this->assertTrue(str_starts_with(ManualAnswer::NOT_INDEXED, 'Je ne trouve pas'));
    }

    public function testAnAnswerWithoutSourceIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Réponse sans source refusée');

        ManualAnswer::cited('Lavez le filtre.', []);
    }

    public function testAnEmptyAnswerIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Réponse vide');

        ManualAnswer::cited('  ', [['manual' => self::manual(), 'page' => 1]]);
    }

    public function testAPageBelowOneIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Page de manuel invalide : 0');

        ManualAnswer::cited('Lavez le filtre.', [['manual' => self::manual(), 'page' => 0]]);
    }

    private static function manual(): Manual
    {
        return new Manual('srk-zs-w-utilisation', 'Manuel de l\'utilisateur SRK ZS-W (test)', ManualType::Utilisation, ['fr'], 'https://manuels.example/srk.pdf', true, ['SRK25ZS-W'], []);
    }
}
