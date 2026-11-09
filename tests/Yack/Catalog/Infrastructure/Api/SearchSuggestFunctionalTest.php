<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Api;

use App\Entity\Taxonomy\Taxon;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Suggestions of "Rechercher dans la liste" (GET /api/v2/shop/yack/search/suggest?q=&category=) on the demo
 * catalogue (make yack-reset), with the engine of the environment (database by default): the products of the
 * category and of its subcategories, its subcategories only. Skipped when the database has no demo catalogue.
 *
 * @internal
 */
#[Group('functional')]
final class SearchSuggestFunctionalTest extends WebTestCase
{
    private const COMPRESSORS = ['YKP-CMP-EVO-08', 'YKP-CMP-HYD-11', 'YKP-CMP-KAZ-35', 'YKP-CMP-THD'];

    public function testTheWholeCatalogueWithoutCategory(): void
    {
        [$products, $categories] = $this->suggest($this->client(), 'compresseur');

        $this->assertSame(self::COMPRESSORS, $products);
        $this->assertSame(['parts_compresseurs'], $categories);
    }

    public function testTheSparePartsListProposesItsFamilies(): void
    {
        [$products, $categories] = $this->suggest($this->client(), 'compresseur', 'clik_yack_parts');

        $this->assertSame(self::COMPRESSORS, $products);
        $this->assertSame(['parts_compresseurs'], $categories);
    }

    public function testAFamilyProposesItsOwnProductsAndNoCategory(): void
    {
        [$products, $categories] = $this->suggest($this->client(), 'compresseur', 'parts_compresseurs');

        $this->assertSame(self::COMPRESSORS, $products);
        $this->assertSame([], $categories);
    }

    public function testAnotherBranchOrAnUnknownCategoryProposesNothing(): void
    {
        $client = $this->client();

        $this->assertSame([[], []], $this->suggest($client, 'compresseur', 'clik_yack'));
        $this->assertSame([[], []], $this->suggest($client, 'compresseur', 'inconnue'));
    }

    private function client(): KernelBrowser
    {
        $client = static::createClient();
        try {
            $parts = static::getContainer()->get(EntityManagerInterface::class)->getRepository(Taxon::class)->findOneBy(['code' => 'parts_compresseurs']);
        } catch (\Throwable $exception) {
            $this->markTestSkipped('Base de démonstration indisponible : ' . $exception->getMessage());
        }
        if (null === $parts) {
            $this->markTestSkipped('Catalogue de démonstration absent : charger les fixtures (make yack-reset).');
        }

        return $client;
    }

    /** @return array{list<string>, list<string>} codes of the suggested products (sorted) and categories */
    private function suggest(KernelBrowser $client, string $text, ?string $category = null): array
    {
        $client->request('GET', '/api/v2/shop/yack/search/suggest', array_filter(['q' => $text, 'category' => $category]), [], ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseIsSuccessful();

        /** @var array{products: list<array{code: string}>, categories: list<array{code: string}>} $answer */
        $answer = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $products = array_column($answer['products'], 'code');
        sort($products);

        return [$products, array_column($answer['categories'], 'code')];
    }
}
