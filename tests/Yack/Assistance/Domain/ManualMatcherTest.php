<?php

declare(strict_types=1);

namespace App\Tests\Yack\Assistance\Domain;

use App\Yack\Assistance\Domain\Manual;
use App\Yack\Assistance\Domain\ManualMatch;
use App\Yack\Assistance\Domain\ManualMatcher;
use App\Yack\Assistance\Domain\ManualProduct;
use App\Yack\Assistance\Domain\ManualType;
use App\Yack\Assistance\Domain\MatchLevel;
use App\Yack\Assistance\Domain\MatchReason;
use App\Yack\Assistance\Domain\ModelReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ManualMatcher::class)]
#[CoversClass(ManualMatch::class)]
#[CoversClass(MatchLevel::class)]
#[CoversClass(MatchReason::class)]
#[CoversClass(ManualProduct::class)]
final class ManualMatcherTest extends TestCase
{
    private ManualMatcher $matcher;

    #[\Override]
    protected function setUp(): void
    {
        $this->matcher = new ManualMatcher();
    }

    /** @return \Iterator<string, array{string, list<string>}> */
    public static function levels(): \Iterator
    {
        yield 'référence identique' => ['SRK25ZS-W', ['srk-zs-w-installation exact reference_identique']];
        yield 'saisie libre de la même référence' => ['srk25zs w', ['srk-zs-w-installation exact reference_identique']];
        yield 'lecture corrigée' => ['SRK2SZS-W', ['srk-zs-w-installation exact reference_identique']];
        yield 'autre variante de suffixe' => ['SRK25ZS-WF', ['srk-zs-w-installation serie variante_suffixe']];
        yield 'sans suffixe' => ['SRK25ZS', ['srk-zs-w-installation serie variante_suffixe']];
        yield 'autre puissance' => ['SRK50ZS-W', ['srk-zs-w-installation serie autre_puissance']];
        yield 'autre puissance et options' => ['SRK50ZS-WFT', ['srk-zs-w-installation serie autre_puissance']];
        yield 'autre type d\'appareil' => ['FDT71VH', []];
        yield 'autre groupe de même série' => ['SRC25ZS-W', []];
    }

    /** @param list<string> $expected */
    #[DataProvider('levels')]
    public function testTheLevelOfADocumentCoveringTwoModels(string $input, array $expected): void
    {
        $manual = self::manual('srk-zs-w-installation', ManualType::Installation, ['SRK20ZS-W', 'SRK25ZS-W']);

        $this->assertSame($expected, self::summary($this->match($input, [$manual])));
    }

    public function testADocumentOfAnotherSeriesOfTheSameTypeOnlyGivesGeneralDocuments(): void
    {
        $manuals = [
            self::manual('srk-zs-w-installation', ManualType::Installation, ['SRK20ZS-W', 'SRK25ZS-W']),
            self::manual('srk-zs-w-technique', ManualType::Technique, ['SRK25ZS-W']),
            self::manual('srk-zs-w-brochure', ManualType::Brochure, ['SRK25ZS-W']),
            self::manual('srk-zs-w-telecommande', ManualType::Telecommande, ['SRK25ZS-W']),
            self::manual('srk-zs-w-utilisation', ManualType::Utilisation, ['SRK20ZS-W', 'SRK25ZS-W']),
            self::manual('src-zs-w-utilisation', ManualType::Utilisation, ['SRC25ZS-W']),
        ];

        $matches = $this->match('SRK25ZSX-W', $manuals);

        $this->assertSame([
            'srk-zs-w-utilisation famille meme_type_appareil',
            'srk-zs-w-telecommande famille meme_type_appareil',
            'srk-zs-w-brochure famille meme_type_appareil',
        ], self::summary($matches));
        $this->assertSame(MatchLevel::Family, ManualMatcher::levelOf($matches));
        $this->assertFalse(MatchLevel::Family->isReliable());
    }

    public function testNoFamilyLevelWhenADocumentMatchesAtSeriesLevel(): void
    {
        $manuals = [
            self::manual('srk-zsx-w-installation', ManualType::Installation, ['SRK20ZSX-W']),
            self::manual('srk-zs-w-utilisation', ManualType::Utilisation, ['SRK25ZS-W']),
        ];

        $this->assertSame(['srk-zsx-w-installation serie autre_puissance'], self::summary($this->match('SRK25ZSX-W', $manuals)));
    }

    public function testTheFamilyLevelIsLimitedToFiveDocuments(): void
    {
        $manuals = [];
        foreach (range(1, 7) as $i) {
            $manuals[] = self::manual('srk-zs-w-utilisation-' . $i, ManualType::Utilisation, ['SRK25ZS-W'], year: (string) (2010 + $i));
        }

        $matches = $this->match('SRK25ZSX-W', $manuals);

        $this->assertCount(ManualMatcher::FAMILY_LIMIT, $matches);
        $this->assertSame('srk-zs-w-utilisation-7', $matches[0]->manual->id);
        $this->assertSame('srk-zs-w-utilisation-3', $matches[4]->manual->id);
    }

    public function testADocumentReachedAtExactLevelIsNotRepeatedAtSeriesLevel(): void
    {
        $manuals = [
            self::manual('srk-zs-installation', ManualType::Installation, ['SRK20ZS-W', 'SRK25ZS-W', 'SRK25ZS-WF', 'SRK35ZS-W']),
            self::manual('srk-zs-wf-installation', ManualType::Installation, ['SRK25ZS-WF']),
        ];

        $this->assertSame([
            'srk-zs-installation exact reference_identique',
            'srk-zs-wf-installation serie variante_suffixe',
        ], self::summary($this->match('SRK25ZS-W', $manuals)));
    }

    public function testCatalogCodesGiveTheExactLevel(): void
    {
        $manual = self::manual('srk-zs-wft-brochure', ManualType::Brochure, [], codes: ['SRK25ZS-WFT']);

        $matches = $this->match('srk25zs wft', [$manual]);

        $this->assertSame(['srk-zs-wft-brochure exact reference_identique'], self::summary($matches));
        $this->assertSame(MatchLevel::Exact, ManualMatcher::levelOf($matches));
        $this->assertTrue(MatchLevel::Exact->isReliable());
    }

    /** @return \Iterator<string, array{string, list<string>}> */
    public static function unstructured(): \Iterator
    {
        yield 'télécommande' => ['RC-EX3D', ['rc-ex3d-utilisation exact reference_identique']];
        yield 'gainable sans puissance' => ['FDCW-VNX-A', ['fdcw-vnx-installation exact reference_identique']];
        yield 'ballon Yack' => ['YORABTM200', ['yack-yorabtm-200 exact reference_identique']];
        yield 'voisin sans document' => ['RC-EX3B', []];
    }

    /** @param list<string> $expected */
    #[DataProvider('unstructured')]
    public function testAnUnstructuredReferenceOnlyMatchesExactly(string $input, array $expected): void
    {
        $manuals = [
            self::manual('rc-ex3d-utilisation', ManualType::Utilisation, ['RC-EX3D']),
            self::manual('rc-ex3a-utilisation', ManualType::Utilisation, ['RC-EX3A']),
            self::manual('fdcw-vnx-installation', ManualType::Installation, ['FDCW-VNX-A']),
            self::manual('fdcw-vnx-w-installation', ManualType::Installation, ['FDCW60VNX-W']),
            self::manual('yack-yorabtm-200', ManualType::Utilisation, ['YORABTM200']),
            self::manual('yack-yorabtm-270', ManualType::Utilisation, ['YORABTM270']),
        ];

        $this->assertSame($expected, self::summary($this->match($input, $manuals)));
    }

    public function testAReferenceOfTheCatalogFamilyOfACoveredModelIsAtSeriesLevel(): void
    {
        $products = [new ManualProduct('YORABTM450', 'Ballon thermodynamique 450 L', 'Yack', ['YORABTM200', 'YORABTM270', 'YORABTM450'])];
        $manuals = [self::manual('yack-yorabtm-450', ManualType::Utilisation, [], codes: ['YORABTM450'])];

        $this->assertSame(['yack-yorabtm-450 serie autre_puissance'], self::summary($this->match('YORABTM270', $manuals, $products)));
        $this->assertSame(['yack-yorabtm-450 exact reference_identique'], self::summary($this->match('YORABTM450', $manuals, $products)));
        $this->assertSame([], self::summary($this->match('YORABTM270', $manuals)));
        $this->assertSame([], self::summary($this->match('YORABTMHE200', $manuals, $products)));
    }

    public function testAFamilyCoveredThroughAnotherMemberAlsoCounts(): void
    {
        $products = [new ManualProduct('SRK20ZS-WFT', 'Mural Titane 2,0 kW', 'MHI', ['SRK15ZS-WFT', 'SRK20ZS-WFT', 'SRK25ZS-WFT'])];
        $manuals = [self::manual('yack-titane-fiche', ManualType::FicheTechnique, [], codes: ['SRK15ZS-WFT'])];

        $this->assertSame(['yack-titane-fiche serie autre_puissance'], self::summary($this->match('SRK25ZS-WFT', $manuals, $products)));
        $this->assertSame(['SRK20ZS-WFT', 'SRK15ZS-WFT', 'SRK25ZS-WFT'], $products[0]->codes());
    }

    public function testOrderInsideAGroup(): void
    {
        $manuals = [
            self::manual('d-utilisation-fr', ManualType::Utilisation, ['SRK25ZS-W'], ['fr']),
            self::manual('c-installation-en-2021', ManualType::Installation, ['SRK25ZS-W'], ['en'], year: '2021'),
            self::manual('b-installation-fr-non-officiel', ManualType::Installation, ['SRK25ZS-W'], ['fr'], official: false, year: '2023'),
            self::manual('a-installation-fr-2019', ManualType::Installation, ['SRK25ZS-W'], ['en', 'fr'], year: '2019'),
            self::manual('e-installation-fr-2019-b', ManualType::Installation, ['SRK25ZS-W'], ['fr'], year: '2019', title: 'B'),
            self::manual('f-installation-fr-2019-a', ManualType::Installation, ['SRK25ZS-W'], ['fr'], year: '2019', title: 'a'),
            self::manual('g-installation-fr-sans-annee', ManualType::Installation, ['SRK25ZS-W'], ['fr']),
            self::manual('h-installation-fr-2022', ManualType::Installation, ['SRK25ZS-W'], ['fr'], year: '2022'),
        ];

        $this->assertSame([
            'h-installation-fr-2022',
            'f-installation-fr-2019-a',
            'a-installation-fr-2019',
            'e-installation-fr-2019-b',
            'g-installation-fr-sans-annee',
            'b-installation-fr-non-officiel',
            'c-installation-en-2021',
            'd-utilisation-fr',
        ], array_map(static fn (ManualMatch $match): string => $match->manual->id, $this->match('SRK25ZS-W', $manuals)));
    }

    public function testGroupsComeByLevelThenReason(): void
    {
        $manuals = [
            self::manual('srk-zs-w-autre-puissance', ManualType::Installation, ['SRK35ZS-W']),
            self::manual('srk-zs-wf-variante', ManualType::Installation, ['SRK25ZS-WF']),
            self::manual('srk-zs-w-brochure', ManualType::Brochure, ['SRK25ZS-W']),
        ];

        $matches = $this->match('SRK25ZS-W', $manuals);

        $this->assertSame([
            'srk-zs-w-brochure exact reference_identique',
            'srk-zs-wf-variante serie variante_suffixe',
            'srk-zs-w-autre-puissance serie autre_puissance',
        ], self::summary($matches));
        $this->assertSame(MatchLevel::Exact, ManualMatcher::levelOf($matches));
        $this->assertSame(MatchLevel::Series, ManualMatcher::levelOf(\array_slice($matches, 1)));
        $this->assertSame(MatchLevel::None, ManualMatcher::levelOf([]));
        $this->assertTrue(MatchLevel::Series->isReliable());
        $this->assertFalse(MatchLevel::None->isReliable());
    }

    /**
     * @param list<Manual>        $manuals
     * @param list<ManualProduct> $products
     *
     * @return list<ManualMatch>
     */
    private function match(string $input, array $manuals, array $products = []): array
    {
        $reference = ModelReference::fromInput($input);
        \assert(null !== $reference);

        return $this->matcher->match($reference, $manuals, $products);
    }

    /**
     * @param list<ManualMatch> $matches
     *
     * @return list<string>
     */
    private static function summary(array $matches): array
    {
        return array_map(static fn (ManualMatch $match): string => sprintf('%s %s %s', $match->manual->id, $match->level->value, $match->reason->value), $matches);
    }

    /**
     * @param list<string> $models
     * @param list<string> $languages
     * @param list<string> $codes
     */
    private static function manual(string $id, ManualType $type, array $models, array $languages = ['fr'], bool $official = true, ?string $year = null, ?string $title = null, array $codes = []): Manual
    {
        return new Manual($id, $title ?? $id, $type, $languages, 'https://mhi.example/' . $id . '.pdf', $official, $models, $codes, year: $year);
    }
}
