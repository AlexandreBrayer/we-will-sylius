<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain;

use App\Yack\Catalog\Domain\SearchVocabulary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SearchVocabulary::class)]
final class SearchVocabularyTest extends TestCase
{
    public function testAWordLeadsToTheOtherExpressionsOfItsGroup(): void
    {
        $this->assertSame(['pompe à chaleur', 'pompes à chaleur'], SearchVocabulary::synonymsOf('pac'));
        $this->assertSame(['clim', 'climatiseur', 'climatiseurs'], SearchVocabulary::synonymsOf('climatisation'));
        $this->assertSame(['vrf', 'vrv'], SearchVocabulary::synonymsOf('DRV'));
    }

    public function testCaseAndAccentsAreIgnored(): void
    {
        $this->assertSame(['thermostat'], SearchVocabulary::synonymsOf('Regulation'));
        $this->assertSame(['ue', 'groupe extérieur'], SearchVocabulary::synonymsOf(' UNITE EXTÉRIEURE '));
    }

    public function testAWordOutsideTheVocabularyHasNoSynonym(): void
    {
        $this->assertSame([], SearchVocabulary::synonymsOf('evolys'));
        $this->assertSame([], SearchVocabulary::synonymsOf(''));
    }

    public function testFold(): void
    {
        $this->assertSame('chauffe-eau electrique a accumulation, coeur', SearchVocabulary::fold(' Chauffe-eau Électrique à accumulation, CŒUR '));
    }

    public function testTheGroupsAreWrittenInLowerCaseWithoutRepeat(): void
    {
        $seen = [];
        foreach (SearchVocabulary::SYNONYMS as $group) {
            $this->assertTrue(count($group) >= 2, implode(', ', $group));
            foreach ($group as $expression) {
                $this->assertSame(mb_strtolower(trim($expression)), $expression);
                $this->assertFalse(isset($seen[SearchVocabulary::fold($expression)]), $expression);
                $seen[SearchVocabulary::fold($expression)] = true;
            }
        }
    }
}
