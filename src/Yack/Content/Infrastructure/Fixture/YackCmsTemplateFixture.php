<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Fixture;

use App\Entity\Content\CmsTemplate;
use App\Yack\Content\Domain\CmsPageStatus;
use App\Yack\Content\Domain\CmsTemplateAssignments;
use App\Yack\Content\Domain\CmsTemplateType;
use App\Yack\Content\Domain\PageBlocks;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/**
 * Seeds the CMS templates (config/yack/fixtures_cms.yaml), after the catalog: blocks go through
 * PageBlocks (same rules as the API), assignments through CmsTemplateAssignments (same data as the
 * back-office « Template CMS » fields). No grapesData: the builder rebuilds its canvas from the blocks.
 * Containers hold their blocks as the API does (`children`, `columns`), checked by PageBlocks too.
 */
final class YackCmsTemplateFixture extends AbstractFixture
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CmsTemplateAssignments $assignments,
    ) {
    }

    public function getName(): string
    {
        return 'yack_cms_template';
    }

    /**
     * @param array{templates: list<array{name: string, type: string, default: bool, status: string, products: list<string>, taxons: list<string>, blocks: list<array{type: string, props: array<string, string>, style: array<string, string>, children?: mixed, columns?: mixed}>}>} $options
     */
    public function load(array $options): void
    {
        foreach ($options['templates'] as $definition) {
            $type = CmsTemplateType::from($definition['type']);
            $template = new CmsTemplate($type, $definition['name']);
            $template->setStatus(CmsPageStatus::from($definition['status']));
            $template->setDefault($definition['default']);
            $template->setBlocks(PageBlocks::normalize($definition['blocks'], $type));

            $this->entityManager->persist($template);
            $this->entityManager->flush();

            $this->assignments->replace($template, $definition['products'], $definition['taxons']);
        }
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->arrayNode('templates')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('name')->isRequired()->cannotBeEmpty()->end()
                            ->enumNode('type')->values(['product', 'taxon'])->isRequired()->end()
                            ->booleanNode('default')->defaultFalse()->end()
                            ->enumNode('status')->values(['draft', 'published'])->defaultValue('published')->end()
                            ->arrayNode('products')->scalarPrototype()->end()->end()
                            ->arrayNode('taxons')->scalarPrototype()->end()->end()
                            ->arrayNode('blocks')
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('type')->isRequired()->end()
                                        ->arrayNode('props')
                                            ->useAttributeAsKey('name')
                                            ->normalizeKeys(false)
                                            ->scalarPrototype()->end()
                                        ->end()
                                        // Style of the block (BlockStyle): named values of the charte, or free values (deviations, shown in the
                                        // back-office report) since fixtures write at the `libre` level.
                                        ->arrayNode('style')
                                            ->useAttributeAsKey('name')
                                            ->normalizeKeys(false)
                                            ->scalarPrototype()->end()
                                        ->end()
                                        // Blocks of a section / columns of a colonnes block (PageBlocks::normalize() validates them).
                                        ->variableNode('children')->end()
                                        ->variableNode('columns')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
