<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Fixture;

use App\Entity\Content\CmsPage;
use App\Yack\Content\Domain\PageBlocks;
use App\Yack\Content\Infrastructure\Fixture\YackCmsPageFixture;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
#[CoversClass(YackCmsPageFixture::class)]
final class YackCmsPageFixtureTest extends TestCase
{
    public function testABlockMayCarryAStyleAndFreeValuesAreKept(): void
    {
        $pages = $this->load(['pages' => ['demo' => [
            'title' => 'Démo',
            'status' => 'draft',
            'blocks' => [
                ['type' => 'cta', 'props' => ['title' => 'A'], 'style' => ['fond' => '#FF7700', 'alignement' => 'centre']],
                ['type' => 'faq', 'props' => ['title' => 'B']],
            ],
        ]]]);

        $this->assertCount(1, $pages);
        $this->assertSame('demo', $pages[0]->getSlug());
        $this->assertFalse($pages[0]->isPublished());
        $this->assertSame(['fond' => '#ff7700', 'alignement' => 'centre'], $pages[0]->getBlocks()[0]['style'] ?? null);
        $this->assertSame([], $pages[0]->getBlocks()[1]['style'] ?? null);
    }

    public function testEveryPageOfTheDemoLoadsAndOnlyTheDemoPageHoldsDeviations(): void
    {
        /** @var array{sylius_fixtures: array{suites: array{yack: array{fixtures: array{yack_cms_page: array{options: array<string, mixed>}}}}}} $fixtures */
        $fixtures = Yaml::parseFile(\dirname(__DIR__, 5) . '/config/yack/fixtures_cms.yaml');

        $pages = $this->load($fixtures['sylius_fixtures']['suites']['yack']['fixtures']['yack_cms_page']['options']);

        $withDeviations = [];
        foreach ($pages as $page) {
            if ([] !== PageBlocks::deviations($page->getBlocks())) {
                $withDeviations[] = $page->getSlug();
            }
        }
        $this->assertGreaterThan(10, \count($pages));
        $this->assertSame(['demo-ecarts-de-charte'], $withDeviations);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<CmsPage>
     */
    private function load(array $options): array
    {
        /** @var list<CmsPage> $persisted */
        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $page) use (&$persisted): void {
            if ($page instanceof CmsPage) {
                $persisted[] = $page;
            }
        });
        $entityManager->expects($this->once())->method('flush');
        $fixture = new YackCmsPageFixture($entityManager);

        /** @var array{pages: array<string, array{title: string, meta_title: ?string, meta_description: ?string, status: string, blocks: list<array{type: string, props: array<string, string>, style: array<string, string>}>}>} $processed */
        $processed = (new Processor())->process($fixture->getConfigTreeBuilder()->buildTree(), [$options]);
        $fixture->load($processed);

        return $persisted;
    }
}
