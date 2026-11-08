<?php

declare(strict_types=1);

namespace App\Tests\Yack\Assistance\Application;

use App\Yack\AfterSales\Application\ApplianceModelsInterface;
use App\Yack\Assistance\Application\FindManuals;
use App\Yack\Assistance\Application\ManualSearch;
use App\Yack\Assistance\Domain\Manual;
use App\Yack\Assistance\Domain\ManualLibrary;
use App\Yack\Assistance\Domain\ManualProduct;
use App\Yack\Assistance\Domain\ManualType;
use App\Yack\Catalog\Application\ProductReferenceReaderInterface;
use App\Yack\Catalog\Domain\ProductReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Base, parc installé et catalogue écrits à la main (documents inventés) : tourne sans vendor/.
 *
 * @internal
 */
#[CoversClass(FindManuals::class)]
#[CoversClass(ManualSearch::class)]
final class FindManualsTest extends TestCase
{
    private const SERIAL = 'YK19KAZ35S004567';

    /** @var list<string> */
    private array $serialLookups = [];

    /** @var list<string> */
    private array $catalogLookups = [];

    #[\Override]
    protected function setUp(): void
    {
        $this->serialLookups = [];
        $this->catalogLookups = [];
    }

    public function testAReferenceTypedWithASpaceFindsTheDocumentsOfTheModel(): void
    {
        $search = $this->find(q: 'SRK25ZS W');

        $this->assertSame('SRK25ZS W', $search->query);
        $this->assertSame('SRK25ZS W', $search->reference?->read);
        $this->assertSame('SRK25ZS-W', $search->reference?->canonical);
        $this->assertSame('exact', $search->level()->value);
        $this->assertSame([
            ['exact', 'reference_identique', 'reference', ['srk-zs-w-installation']],
            ['serie', 'variante_suffixe', 'reference', ['srk-zs-wft-utilisation']],
        ], self::groups($search));
        $this->assertSame(['SRK25ZS-W', 'srk25zs-w-mural'], [$search->product?->code, $search->product?->slug]);
        $this->assertSame(['MHI', 'catalogue'], [$search->brand, $search->brandSource]);
        $this->assertNull($search->serial);
        $this->assertFalse($search->conflict);
        $this->assertTrue($search->askable());
        $this->assertSame([], $search->choices);
        $this->assertSame(['SRK25ZS W'], $this->serialLookups);
    }

    public function testAKnownSerialSearchesTheDocumentsOfEachFinishedProduct(): void
    {
        $search = $this->find(q: self::SERIAL);

        $this->assertSame(self::SERIAL, $search->serial);
        $this->assertTrue($search->serialKnown());
        $this->assertSame(['SRK25ZS-WFT', 'SRC25ZS-W2'], $search->serialProductCodes);
        $this->assertNull($search->reference);
        $this->assertFalse($search->conflict);
        $this->assertSame([
            ['exact', 'reference_identique', 'serial', ['src-zs-w2-installation', 'srk-zs-wft-utilisation']],
            ['serie', 'variante_suffixe', 'serial', ['srk-zs-w-installation']],
        ], self::groups($search));
        $this->assertSame('SRK25ZS-WFT', $search->product?->code);
        $this->assertSame([null, null], [$search->brand, $search->brandSource]);
    }

    public function testASerialAndAReferenceOfAnotherApplianceAreInConflictAndBothResultsStay(): void
    {
        $search = $this->find(reference: 'SRK35ZS-W', serial: self::SERIAL);

        $this->assertTrue($search->conflict);
        $this->assertSame('SRK35ZS-W', $search->query);
        $this->assertSame('SRK35ZS-W', $search->reference?->canonical);
        $this->assertSame(['SRK25ZS-WFT', 'SRC25ZS-W2'], $search->serialProductCodes);
        $this->assertSame([
            ['exact', 'reference_identique', 'serial', ['src-zs-w2-installation', 'srk-zs-wft-utilisation']],
            ['exact', 'reference_identique', 'reference', ['srk-zs-w-installation']],
            ['serie', 'variante_suffixe', 'serial', ['srk-zs-w-installation']],
            ['serie', 'autre_puissance', 'reference', ['srk-zs-wft-utilisation']],
        ], self::groups($search));
        $this->assertNull($search->product);
        $this->assertSame(['MHI', 'deduite'], [$search->brand, $search->brandSource]);
    }

    public function testAReferenceThatIsOneOfTheFinishedProductsOfTheSerialIsNoConflict(): void
    {
        $search = $this->find(reference: 'srk25zs-wft', serial: 'yk19 kaz35s004567');

        $this->assertFalse($search->conflict);
        $this->assertSame(self::SERIAL, $search->serial);
        $this->assertSame(['serial'], array_values(array_unique(array_column(self::groups($search), 2))));
        $this->assertSame('SRK25ZS-WFT', $search->product?->code);
    }

    public function testAnUnknownSerialWithoutReferenceFindsNothing(): void
    {
        $search = $this->find(serial: 'ABC123456');

        $this->assertSame('ABC123456', $search->serial);
        $this->assertFalse($search->serialKnown());
        $this->assertNull($search->serialProductCodes);
        $this->assertSame('aucun', $search->level()->value);
        $this->assertSame([], $search->groups);
        $this->assertFalse($search->askable());
        $this->assertSame(['ABC123456'], $this->serialLookups);
    }

    public function testAnUnknownSerialKeepsTheResultsOfTheReference(): void
    {
        $search = $this->find(reference: 'SRK25ZS-W', serial: 'ABC123456');

        $this->assertFalse($search->conflict);
        $this->assertFalse($search->serialKnown());
        $this->assertSame('exact', $search->level()->value);
    }

    public function testAProductNameGivesTheModelsToChooseFrom(): void
    {
        $search = $this->find(q: 'mural titane');

        $this->assertSame(['SRK25ZS-WT', 'SRK35ZS-WT', 'SRK50ZS-WT'], array_map(static fn (ManualProduct $product): string => $product->code, $search->choices));
        $this->assertFalse($search->moreChoices);
        $this->assertSame([], $search->groups);
        $this->assertNull($search->reference);
        $this->assertSame('aucun', $search->level()->value);
    }

    public function testWordsAreFoundWhateverTheirAccentsAndCase(): void
    {
        $this->assertSame(['SRK35ZS-WT'], array_map(static fn (ManualProduct $product): string => $product->code, $this->find(q: 'INTEGRE 3,5')->choices));
        $this->assertSame(['YORABTM200'], array_map(static fn (ManualProduct $product): string => $product->code, $this->find(q: 'ballon')->choices));
    }

    public function testMoreThanEightProductsGiveTheFirstEightAndAskToNarrowDown(): void
    {
        $products = [];
        for ($i = 20; $i <= 100; $i += 10) {
            $products[] = new ManualProduct(sprintf('SRK%dZZ-W', $i), sprintf('Mural ZZ %d', $i), 'MHI');
        }
        $search = $this->finder(self::library([], $products))(q: 'mural zz');

        $this->assertCount(9, $products);
        $this->assertSame(
            ['SRK20ZZ-W', 'SRK30ZZ-W', 'SRK40ZZ-W', 'SRK50ZZ-W', 'SRK60ZZ-W', 'SRK70ZZ-W', 'SRK80ZZ-W', 'SRK90ZZ-W'],
            array_map(static fn (ManualProduct $product): string => $product->code, $search->choices),
        );
        $this->assertTrue($search->moreChoices);
        $this->assertSame([], $search->groups);
        $this->assertNull($search->reference);
        $this->assertSame('aucun', $search->level()->value);
    }

    public function testEightProductsAreAllChoicesWithNothingMore(): void
    {
        $products = [];
        for ($i = 20; $i <= 90; $i += 10) {
            $products[] = new ManualProduct(sprintf('SRK%dZZ-W', $i), sprintf('Mural ZZ %d', $i), 'MHI');
        }
        $search = $this->finder(self::library([], $products))(q: 'mural zz');

        $this->assertCount(FindManuals::MAX_CHOICES, $search->choices);
        $this->assertFalse($search->moreChoices);
    }

    public function testAnUnknownWordStaysAReferenceAndASentenceDoesNot(): void
    {
        $word = $this->find(q: 'ZZZ');
        $this->assertSame('aucun', $word->level()->value);
        $this->assertSame(['ZZZ', false], [$word->reference?->canonical, $word->reference?->structured]);

        $sentence = $this->find(q: 'notice introuvable');
        $this->assertSame('aucun', $sentence->level()->value);
        $this->assertNull($sentence->reference);
        $this->assertSame('notice introuvable', $sentence->query);
    }

    public function testAReferenceThatCannotBeSplitIsFoundWhenTheLibraryKnowsIt(): void
    {
        $search = $this->find(q: 'yorabtm200');

        $this->assertSame([['exact', 'reference_identique', 'reference', ['yorabtm-notice']]], self::groups($search));
        $this->assertSame(['Yack', 'catalogue'], [$search->brand, $search->brandSource]);
    }

    public function testAnotherCapacityOfTheSeriesIsASeriesMatchWithADeducedBrand(): void
    {
        $search = $this->find(reference: 'SRK50ZS-W');

        $this->assertSame('serie', $search->level()->value);
        $this->assertSame([['serie', 'autre_puissance', 'reference', ['srk-zs-w-installation', 'srk-zs-wft-utilisation']]], self::groups($search));
        $this->assertSame(['MHI', 'deduite'], [$search->brand, $search->brandSource]);
        $this->assertNull($search->product);
        $this->assertSame(['SRK50ZS-W'], $this->catalogLookups);
        $this->assertSame([], $this->serialLookups);
    }

    public function testAMisreadReferenceIsCorrected(): void
    {
        $search = $this->find(q: 'SRK2SZS-W');

        $this->assertSame(['SRK2SZS-W', 'SRK25ZS-W', true], [$search->reference?->read, $search->reference?->canonical, $search->reference?->corrected]);
        $this->assertSame('exact', $search->level()->value);
    }

    public function testAnotherSeriesOfTheSameApplianceTypeGivesGeneralDocumentsOnlyAndIsNotAskable(): void
    {
        $search = $this->find(q: 'SRK25ZSY-W');

        $this->assertSame('famille', $search->level()->value);
        $this->assertSame([['famille', 'meme_type_appareil', 'reference', ['srk-zs-wft-utilisation', 'srk-zsx-telecommande']]], self::groups($search));
        $this->assertFalse($search->askable());
    }

    public function testTheFamilyLevelIsDroppedWhenAnotherFinishedProductOfTheSerialHasItsDocuments(): void
    {
        $search = $this->find(serial: 'YK00MIXTE0000001');

        $this->assertSame([
            ['exact', 'reference_identique', 'serial', ['srk-zs-wft-utilisation']],
            ['serie', 'variante_suffixe', 'serial', ['srk-zs-w-installation']],
        ], self::groups($search));
    }

    public function testTheFamilyLevelOfASerialKeepsFiveDocumentsAtMost(): void
    {
        $general = [];
        foreach (['SRK', 'SRC'] as $prefix) {
            for ($i = 1; $i <= 4; ++$i) {
                $general[] = new Manual(strtolower($prefix) . '-zz-brochure-' . $i, sprintf('Brochure %s %d (test)', $prefix, $i), ManualType::Brochure, ['fr'], 'https://manuels.example/b.pdf', true, [$prefix . '25ZZ-W'], []);
            }
        }
        $search = $this->finder(self::library($general, []))(serial: 'YK00FAMILLE00001');

        $this->assertSame('famille', $search->level()->value);
        $this->assertCount(5, $search->groups[0]['manuals']);
        $this->assertSame(['src-zz-brochure-1', 'src-zz-brochure-2', 'src-zz-brochure-3', 'src-zz-brochure-4', 'srk-zz-brochure-1'], self::groups($search)[0][3]);
    }

    public function testAKnownSerialWithoutFinishedProductIsKnownWithoutDocuments(): void
    {
        $search = $this->find(q: 'YK00SANSPRODUIT1');

        $this->assertTrue($search->serialKnown());
        $this->assertSame([], $search->serialProductCodes);
        $this->assertSame([], $search->groups);
        $this->assertNull($search->product);
    }

    public function testTheFreeTextWinsOverTheValidatedFields(): void
    {
        $search = $this->find(q: 'SRK25ZS-W', reference: 'SRK35ZS-W', serial: self::SERIAL);

        $this->assertSame('SRK25ZS-W', $search->reference?->canonical);
        $this->assertNull($search->serial);
        $this->assertSame(['SRK25ZS-W'], $this->serialLookups);
    }

    /** @return \Iterator<string, array{string, string, string, array<string, string>}> */
    public static function refusedInputs(): \Iterator
    {
        yield 'rien' => ['', '', '', ['q' => FindManuals::REQUIRED_MESSAGE]];
        yield 'espaces' => ['  ', ' ', '', ['q' => FindManuals::REQUIRED_MESSAGE]];
        yield 'q trop long' => [str_repeat('a', 101), '', '', ['q' => FindManuals::TOO_LONG_MESSAGE]];
        yield 'référence et n° trop longs' => ['', str_repeat('b', 101), str_repeat('1', 101), ['reference' => FindManuals::TOO_LONG_MESSAGE, 'serial' => FindManuals::TOO_LONG_MESSAGE]];
        yield '100 caractères' => [str_repeat('a', 100), '', '', []];
    }

    /** @param array<string, string> $expected */
    #[DataProvider('refusedInputs')]
    public function testProblemsOfTheInput(string $q, string $reference, string $serial, array $expected): void
    {
        $this->assertSame($expected, FindManuals::problems($q, $reference, $serial));
    }

    public function testARefusedInputIsNotSearched(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(FindManuals::REQUIRED_MESSAGE);

        $this->find();
    }

    public function testTheRequiredMessageIsFrench(): void
    {
        $this->assertSame('Saisissez une référence, un modèle ou un n° de série.', FindManuals::REQUIRED_MESSAGE);
        $this->assertSame('Saisie trop longue : 100 caractères au plus.', FindManuals::TOO_LONG_MESSAGE);
    }

    /** @return list<array{string, string, string, list<string>}> niveau, raison, source, ids des documents */
    private static function groups(ManualSearch $search): array
    {
        return array_map(static fn (array $group): array => [
            $group['level']->value,
            $group['reason']->value,
            $group['source'],
            array_map(static fn (Manual $manual): string => $manual->id, $group['manuals']),
        ], $search->groups);
    }

    private function find(string $q = '', string $reference = '', string $serial = ''): ManualSearch
    {
        return $this->finder(self::library(self::manuals(), self::products()))($q, $reference, $serial);
    }

    private function finder(ManualLibrary $library): FindManuals
    {
        $serialLookups = &$this->serialLookups;
        $catalogLookups = &$this->catalogLookups;

        $appliances = new class($serialLookups) implements ApplianceModelsInterface {
            /** @param list<string> $lookups */
            public function __construct(private array &$lookups)
            {
            }

            public function productCodes(string $serialOrServiceCode): ?array
            {
                $this->lookups[] = $serialOrServiceCode;

                return match (strtoupper((string) preg_replace('/[\s-]+/', '', $serialOrServiceCode))) {
                    'YK19KAZ35S004567' => ['SRK25ZS-WFT', 'SRC25ZS-W2'],
                    'YK00MIXTE0000001' => ['SRK25ZS-WFT', 'SRK25ZSY-W'],
                    'YK00FAMILLE00001' => ['SRK99XX-W', 'SRC99XX-W'],
                    'YK00SANSPRODUIT1' => [],
                    default => null,
                };
            }
        };

        $catalog = new class($catalogLookups) implements ProductReferenceReaderInterface {
            /** @param list<string> $lookups */
            public function __construct(private array &$lookups)
            {
            }

            public function find(string $printedReference): ?ProductReference
            {
                $this->lookups[] = $printedReference;

                return match ($printedReference) {
                    'SRK25ZS-W' => new ProductReference('SRK25ZS-W', 'SRK25ZS-W Mural - 2,5kW - R32', 'srk25zs-w-mural', 'MHI'),
                    'SRK25ZS-WFT' => new ProductReference('SRK25ZS-WFT', 'Mural Titane Wifi intégré - 2,5kW - R32', 'mural-titane-srk25zs-wft', 'MHI'),
                    default => null,
                };
            }
        };

        return new FindManuals($library, $appliances, $catalog);
    }

    /**
     * @param list<Manual>        $manuals
     * @param list<ManualProduct> $products
     */
    private static function library(array $manuals, array $products): ManualLibrary
    {
        return new class($manuals, $products) implements ManualLibrary {
            /**
             * @param list<Manual>        $manuals
             * @param list<ManualProduct> $products
             */
            public function __construct(private array $manuals, private array $products)
            {
            }

            public function documents(): array
            {
                return $this->manuals;
            }

            public function products(): array
            {
                return $this->products;
            }
        };
    }

    /** @return list<Manual> */
    private static function manuals(): array
    {
        return [
            new Manual('srk-zs-w-installation', 'Installation manual SRK/SRC ZS-W (test)', ManualType::Installation, ['en', 'fr'], 'https://manuels.example/srk-zs-w.pdf', true,
                ['SRC20ZS-W', 'SRC25ZS-W', 'SRK20ZS-W', 'SRK25ZS-W', 'SRK35ZS-W'], ['SRK25ZS-W'], 'MHI', 'TEST-001', '2019', 24, 'textes/srk-zs-w-installation.md', '12-19'),
            new Manual('srk-zs-wft-utilisation', 'Notice d\'utilisation SRK25ZS-WFT (test)', ManualType::Utilisation, ['fr'], 'https://manuels.example/srk-zs-wft.pdf', true,
                ['SRK25ZS-WFT'], ['SRK25ZS-WFT'], 'MHI', text: 'textes/srk-zs-wft-utilisation.md', indexedPages: '3-5'),
            new Manual('src-zs-w2-installation', 'Installation manual SRC25ZS-W2 (test)', ManualType::Installation, ['en'], 'https://manuels.example/src-zs-w2.pdf', true,
                ['SRC25ZS-W2'], ['SRC25ZS-W2'], 'MHI'),
            new Manual('srk-zsx-telecommande', 'Télécommande SRK ZSX (test)', ManualType::Telecommande, ['fr'], 'https://manuels.example/srk-zsx-rc.pdf', true,
                ['SRK25ZSX-W'], [], 'MHI'),
            new Manual('yorabtm-notice', 'Notice ballon YORABTM (test)', ManualType::Utilisation, ['fr'], 'https://manuels.example/yorabtm.pdf', true,
                [], ['YORABTM200'], 'Yack'),
        ];
    }

    /** @return list<ManualProduct> */
    private static function products(): array
    {
        return [
            new ManualProduct('SRK25ZS-W', 'Mural ZS 2,5 kW (test)', 'MHI', ['SRK20ZS-W', 'SRK25ZS-W', 'SRK35ZS-W', 'SRK50ZS-W']),
            new ManualProduct('SRK25ZS-WT', 'Mural Titane Wifi intégré 2,5 kW (test)', 'MHI'),
            new ManualProduct('SRK35ZS-WT', 'Mural Titane Wifi intégré 3,5 kW (test)', 'MHI'),
            new ManualProduct('SRK50ZS-WT', 'Mural Titane Wifi intégré 5 kW (test)', 'MHI'),
            new ManualProduct('YORABTM200', 'Ballon thermodynamique 200 L (test)', 'Yack', ['YORABTM200', 'YORABTM270']),
        ];
    }
}
