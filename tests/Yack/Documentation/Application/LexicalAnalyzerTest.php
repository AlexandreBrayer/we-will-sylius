<?php

declare(strict_types=1);

namespace App\Tests\Yack\Documentation\Application;

use App\Yack\Documentation\Application\LexicalAnalyzer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LexicalAnalyzer::class)]
final class LexicalAnalyzerTest extends TestCase
{
    public function testAccentsAndCaseAreRemoved(): void
    {
        $this->assertSame('reglage du debit', LexicalAnalyzer::normalize('Réglage du DÉBIT'));
        $this->assertSame('ecole francaise oeuvre', LexicalAnalyzer::normalize('École française Œuvre'));
    }

    #[DataProvider('sameTerms')]
    public function testVariantsGiveTheSameTerms(string $a, string $b): void
    {
        $this->assertSame(LexicalAnalyzer::terms($a), LexicalAnalyzer::terms($b));
    }

    /** @return \Iterator<string, array{string, string}> */
    public static function sameTerms(): \Iterator
    {
        yield 'pluriel' => ['liaisons', 'liaison'];
        yield 'accents et casse' => ['ÉTANCHÉITÉ', 'etancheite'];
        yield 'racine' => ['entretenir', 'entretien'];
        yield 'mots vides' => ['Quelle est la longueur de la liaison ?', 'longueur liaison'];
    }

    public function testNumbersAndCodesAreKeptSingleLettersDropped(): void
    {
        $this->assertSame(['code', 'erreur', 'e5', '8', 'kw'], LexicalAnalyzer::terms("Code d'erreur E5 : 8 kW"));
    }

    public function testDuplicatesAreKeptForTheFrequencyAndRemovedForTheQuery(): void
    {
        $this->assertSame(['charge', 'charge', 'fluide'], LexicalAnalyzer::terms('Charge, charges de fluide'));
        $this->assertSame(['charge', 'fluide'], LexicalAnalyzer::distinctTerms('Charge, charges de fluide'));
    }

    public function testQuantities(): void
    {
        $this->assertTrue(LexicalAnalyzer::isQuantity('25'));
        $this->assertTrue(LexicalAnalyzer::isQuantity('kw'));
        $this->assertFalse(LexicalAnalyzer::isQuantity('e5'));
        $this->assertFalse(LexicalAnalyzer::isQuantity('liaiso'));
    }
}
