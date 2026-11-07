<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Meilisearch;

use App\Yack\Catalog\Domain\CategoryDocument;
use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Domain\SearchDocument;
use App\Yack\Catalog\Domain\SearchQuery;
use App\Yack\Catalog\Infrastructure\Meilisearch\MeilisearchClient;
use App\Yack\Catalog\Infrastructure\Meilisearch\MeilisearchIndexes;
use App\Yack\Catalog\Infrastructure\Meilisearch\MeilisearchProductIndex;
use App\Yack\Catalog\Infrastructure\Meilisearch\MeilisearchProductSearch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Real Meilisearch (service of compose.yml, profile "search"): index settings, synonyms, typos and
 * reference prefixes. Indexes prefixed with "yack_test_", rebuilt by the first test.
 * `docker compose exec php vendor/bin/phpunit --group external tests/Yack/Catalog/Infrastructure/Meilisearch`
 *
 * @internal
 */
#[Group('external')]
final class MeilisearchYExternalTest extends TestCase
{
    private static ?MeilisearchProductSearch $search = null;

    #[\Override]
    protected function setUp(): void
    {
        if (null !== self::$search) {
            return;
        }

        $url = getenv('MEILISEARCH_URL');
        $key = getenv('MEILISEARCH_KEY');
        $client = new MeilisearchClient(HttpClient::create(), is_string($url) && '' !== $url ? $url : 'http://meilisearch:7700', is_string($key) ? $key : '');
        $indexes = new MeilisearchIndexes('yack_test_');
        (new MeilisearchProductIndex($client, $indexes))->replaceAll(self::products(), [
            new CategoryDocument('clim_murale', 'Climatiseurs muraux', 'nos-produits/clik-yack/climatisation/muraux', ProductKind::Finished, ['Clik&Yack', 'Climatisation']),
            new CategoryDocument('pac_air_eau', 'PAC air/eau', 'nos-produits/clik-yack/pompes-a-chaleur/air-eau', ProductKind::Finished, ['Clik&Yack', 'Pompes à chaleur']),
        ]);
        self::$search = new MeilisearchProductSearch($client, $indexes);
    }

    /**
     * @param list<string> $expectedFirst codes expected at the top, in this order
     */
    #[DataProvider('provideSearches')]
    public function testSearch(string $text, array $expectedFirst): void
    {
        $codes = self::$search?->search(new SearchQuery($text))->codes ?? [];

        $this->assertSame($expectedFirst, array_slice($codes, 0, count($expectedFirst)), implode(', ', $codes));
    }

    /**
     * @return \Iterator<string, array{string, list<string>}>
     */
    public static function provideSearches(): \Iterator
    {
        yield 'reference prefix' => ['SRK25', ['SRK25ZSP-W1', 'SRK25ZS-WF']];
        yield 'synonym and plural' => ['clim murale', ['YK-CLM-P26']];
        yield 'no accent' => ['pompe a chaleur', ['YK-PAE-M08']];
        yield 'abbreviation' => ['pac air eau', ['YK-PAE-M08']];
        yield 'typo' => ['gainabel', ['YK-CLG-G71']];
        yield 'DRV' => ['drv', ['FDC224KXZRE2']];
        yield 'beginning of a reference with a dash' => ['YK-CLM', ['YK-CLM-P26']];
    }

    public function testAFullReferenceFindsThatProductOnly(): void
    {
        $result = self::$search?->search(new SearchQuery('YK-CLG-G71'));

        $this->assertSame(['YK-CLG-G71'], $result?->codes);
        $this->assertSame(1, $result?->total);
    }

    public function testWhenNoProductHasEveryWordTheLastOnesBecomeOptional(): void
    {
        $this->assertSame('YK-PAE-M08', self::$search?->search(new SearchQuery('pompe a chaleur introuvable'))->codes[0] ?? null);
    }

    public function testAReferenceIsNeverMatchedWithATypo(): void
    {
        $this->assertSame([], self::$search?->search(new SearchQuery('SRK26'))->codes);
    }

    public function testADisabledProductIsNeverFound(): void
    {
        $this->assertFalse(in_array('YK-OLD', self::$search?->search(new SearchQuery('YK-OLD'))->codes ?? [], true));
    }

    public function testSuggestions(): void
    {
        $suggestions = self::$search?->suggest('clim', 6, 3);

        $this->assertSame('YK-CLM-P26', $suggestions?->products[0]->code);
        $this->assertSame('clim_murale', $suggestions?->categories[0]->code);
    }

    /** @return list<SearchDocument> */
    private static function products(): array
    {
        $clim = ['Clik&Yack', 'Climatisation', 'Climatiseurs muraux'];
        $pac = ['Clik&Yack', 'Pompes à chaleur', 'PAC air/eau'];

        return [
            new SearchDocument('SRK25ZSP-W1', 'Mural Premium 2,5 kW R32', 'srk25zsp-w1', true, ProductKind::Finished, 'MHI', 'Premium', 'Produit fini', null, ['clim_murale'], $clim),
            new SearchDocument('SRK35ZSP-W', 'Mural Premium 3,5 kW R32', 'srk35zsp-w', true, ProductKind::Finished, 'MHI', 'Premium', 'Produit fini', null, ['clim_murale'], $clim),
            new SearchDocument('SRK25ZS-WF', 'Mural Diamond 2,5 kW R32', 'srk25zs-wf', true, ProductKind::Finished, 'MHI', 'Diamond', 'Produit fini', null, ['clim_murale'], $clim),
            new SearchDocument('YK-CLM-P26', 'Climatiseur mural Prima 2,6 kW', 'yk-clm-p26', true, ProductKind::Finished, 'Yack', 'Prima', 'Produit fini', null, ['clim_murale'], $clim),
            new SearchDocument('FDC224KXZRE2', 'Groupe extérieur DRV KXZ 22,4 kW', 'fdc224kxzre2', true, ProductKind::Finished, 'MHI', 'KXZ', 'Produit fini', null, ['clim_drv'], ['Clik&Yack', 'Climatisation', 'DRV']),
            new SearchDocument('YK-CLG-G71', 'Gainable basse pression Yack Pro 7,1 kW', 'yk-clg-g71', true, ProductKind::Finished, 'Yack', 'Pro', 'Produit fini', null, ['clim_gainable_cassette'], ['Clik&Yack', 'Climatisation', 'Gainables & cassettes']),
            new SearchDocument('YK-PAE-M08', 'Pompe à chaleur air/eau monobloc Evolys 8 kW', 'yk-pae-m08', true, ProductKind::Finished, 'Yack', 'Evolys', 'Produit fini', null, ['pac_air_eau'], $pac),
            new SearchDocument('YK-ECS-T200', 'Ballon thermodynamique 200 L', 'yk-ecs-t200', true, ProductKind::Finished, 'Hydrolia', 'Aquaflow', 'Produit fini', null, ['ballons_thermodynamiques'], ['Clik&Yack', 'Eau chaude sanitaire']),
            new SearchDocument('YK-OLD', 'Mural ancienne génération', 'yk-old', false, ProductKind::Finished, 'Yack', null, 'Produit fini', null, ['clim_murale'], $clim),
        ];
    }
}
