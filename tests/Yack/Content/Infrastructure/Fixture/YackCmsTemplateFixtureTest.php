<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Fixture;

use App\Entity\Content\CmsTemplate;
use App\Yack\Content\Domain\CmsTemplateAssignments;
use App\Yack\Content\Domain\PageBlocks;
use App\Yack\Content\Infrastructure\Fixture\YackCmsTemplateFixture;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
#[CoversClass(YackCmsTemplateFixture::class)]
final class YackCmsTemplateFixtureTest extends TestCase
{
    public function testABlockOfATemplateMayCarryAStyle(): void
    {
        $templates = $this->load(['templates' => [[
            'name' => 'PAC',
            'type' => 'product',
            'taxons' => ['pac_air_eau'],
            'blocks' => [
                ['type' => 'produit-achat'],
                ['type' => 'cta', 'props' => ['title' => 'A'], 'style' => ['titre' => '44px', 'hautEspace' => 'xl']],
            ],
        ]]], [[[], ['pac_air_eau']]]);

        $this->assertSame('PAC', $templates[0]->getName());
        $this->assertSame([], $templates[0]->getBlocks()[0]['style'] ?? null);
        $this->assertSame(['titre' => '44px', 'hautEspace' => 'xl'], $templates[0]->getBlocks()[1]['style'] ?? null);
    }

    public function testEveryTemplateOfTheDemoLoadsWithinTheCharte(): void
    {
        /** @var array{sylius_fixtures: array{suites: array{yack: array{fixtures: array{yack_cms_template: array{options: array{templates: list<array<string, mixed>>}}}}}}} $fixtures */
        $fixtures = Yaml::parseFile(\dirname(__DIR__, 5) . '/config/yack/fixtures_cms.yaml');
        $options = $fixtures['sylius_fixtures']['suites']['yack']['fixtures']['yack_cms_template']['options'];

        $templates = $this->load($options, null);

        $this->assertCount(\count($options['templates']), $templates);
        foreach ($templates as $template) {
            $this->assertSame([], PageBlocks::deviations($template->getBlocks()), $template->getName());
        }
    }

    /**
     * @param array<string, mixed>                       $options
     * @param ?list<array{list<string>, list<string>}>   $assignments expected products and taxons of each template, null: not checked
     *
     * @return list<CmsTemplate>
     */
    private function load(array $options, ?array $assignments): array
    {
        /** @var list<CmsTemplate> $persisted */
        $persisted = [];
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $template) use (&$persisted): void {
            if ($template instanceof CmsTemplate) {
                $persisted[] = $template;
            }
        });
        $replaced = [];
        $assignmentsPort = $this->createStub(CmsTemplateAssignments::class);
        $assignmentsPort->method('replace')->willReturnCallback(static function (CmsTemplate $template, array $products, array $taxons) use (&$replaced): void {
            $replaced[] = [$products, $taxons];
        });
        $fixture = new YackCmsTemplateFixture($entityManager, $assignmentsPort);

        /** @var array{templates: list<array{name: string, type: string, default: bool, status: string, products: list<string>, taxons: list<string>, blocks: list<array{type: string, props: array<string, string>, style: array<string, string>}>}>} $processed */
        $processed = (new Processor())->process($fixture->getConfigTreeBuilder()->buildTree(), [$options]);
        $fixture->load($processed);

        if (null !== $assignments) {
            $this->assertSame($assignments, $replaced);
        }

        return $persisted;
    }
}
