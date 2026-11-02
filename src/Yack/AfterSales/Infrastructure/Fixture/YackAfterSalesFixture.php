<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Infrastructure\Fixture;

use App\Entity\AfterSales\Model;
use App\Entity\AfterSales\SerialNumber;
use App\Yack\AfterSales\Domain\SerialNumberFormat;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Seeds models (ServiceCode), exploded views with their part markers, and demo serial numbers.
 * Parts must already exist in the catalog (yack_catalog fixture runs first).
 */
final class YackAfterSalesFixture extends AbstractFixture
{
    /**
     * @param ProductRepositoryInterface<ProductInterface> $productRepository
     */
    public function __construct(
        #[Autowire(service: 'sylius.repository.product')]
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getName(): string
    {
        return 'yack_after_sales';
    }

    /**
     * @param array{
     *     models: array<string, array{name: string, family: string, finished_product: ?string, exploded_views: list<array{name: string, image: string, parts: list<array{marker: int, part: string, x: float, y: float, quantity: int}>}>}>,
     *     serials: array<string, list<string>>
     * } $options
     */
    public function load(array $options): void
    {
        $models = [];
        foreach ($options['models'] as $serviceCode => $definition) {
            $model = new Model(
                serviceCode: strtoupper($serviceCode),
                name: $definition['name'],
                family: $definition['family'],
                finishedProduct: null === $definition['finished_product'] ? null : $this->product($definition['finished_product']),
            );

            foreach ($definition['exploded_views'] as $viewDefinition) {
                $view = $model->addExplodedView($viewDefinition['name'], $viewDefinition['image']);
                foreach ($viewDefinition['parts'] as $part) {
                    $view->addPart($part['marker'], $this->product($part['part']), $part['x'], $part['y'], $part['quantity']);
                }
            }

            $this->entityManager->persist($model);
            $models[$model->getServiceCode()] = $model;
        }

        foreach ($options['serials'] as $serial => $serviceCodes) {
            $this->entityManager->persist(new SerialNumber(
                SerialNumberFormat::normalize($serial),
                array_map(
                    static fn (string $code): Model => $models[strtoupper($code)] ?? throw new \InvalidArgumentException(sprintf('Unknown model "%s" for serial "%s".', $code, $serial)),
                    $serviceCodes,
                ),
            ));
        }

        $this->entityManager->flush();
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->arrayNode('models')
                    ->useAttributeAsKey('service_code')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('name')->isRequired()->end()
                            ->scalarNode('family')->isRequired()->end()
                            ->scalarNode('finished_product')->defaultNull()->end()
                            ->arrayNode('exploded_views')
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('name')->isRequired()->end()
                                        ->scalarNode('image')->isRequired()->end()
                                        ->arrayNode('parts')
                                            ->arrayPrototype()
                                                ->children()
                                                    ->integerNode('marker')->isRequired()->end()
                                                    ->scalarNode('part')->isRequired()->end()
                                                    ->floatNode('x')->isRequired()->end()
                                                    ->floatNode('y')->isRequired()->end()
                                                    ->integerNode('quantity')->defaultValue(1)->end()
                                                ->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('serials')
                    ->useAttributeAsKey('serial')
                    ->arrayPrototype()->scalarPrototype()->end()->end()
                ->end()
            ->end()
        ;
    }

    private function product(string $code): ProductInterface
    {
        /** @var ProductInterface|null $product */
        $product = $this->productRepository->findOneBy(['code' => $code]);

        return $product ?? throw new \InvalidArgumentException(sprintf('Product "%s" not found: load the yack_catalog fixture first.', $code));
    }
}
