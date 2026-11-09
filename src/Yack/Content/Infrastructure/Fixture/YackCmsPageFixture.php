<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Fixture;

use App\Entity\Content\CmsPage;
use App\Yack\Content\Domain\CmsPageStatus;
use App\Yack\Content\Domain\PageBlocks;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/**
 * Seeds the CMS pages (config/yack/fixtures_cms.yaml). Blocks go through PageBlocks so a
 * fixture cannot contain anything the builder could not produce. No grapesData: the
 * builder rebuilds its canvas from the blocks on first opening. Containers hold their blocks as the API
 * does (`children` of a section, `columns` of a colonnes block), checked by PageBlocks too.
 */
final class YackCmsPageFixture extends AbstractFixture
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function getName(): string
    {
        return 'yack_cms_page';
    }

    /**
     * @param array{pages: array<string, array{title: string, meta_title: ?string, meta_description: ?string, status: string, blocks: list<array{type: string, props: array<string, string>, style: array<string, string>, children?: mixed, columns?: mixed}>}>} $options
     */
    public function load(array $options): void
    {
        foreach ($options['pages'] as $slug => $definition) {
            $page = new CmsPage((string) $slug, $definition['title']);
            $page->setMetaTitle($definition['meta_title']);
            $page->setMetaDescription($definition['meta_description']);
            $page->setStatus(CmsPageStatus::from($definition['status']));
            $page->setBlocks(PageBlocks::normalize($definition['blocks']));

            $this->entityManager->persist($page);
        }

        $this->entityManager->flush();
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->arrayNode('pages')
                    ->useAttributeAsKey('slug')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('title')->isRequired()->end()
                            ->scalarNode('meta_title')->defaultNull()->end()
                            ->scalarNode('meta_description')->defaultNull()->end()
                            ->enumNode('status')->values(['draft', 'published'])->defaultValue('published')->end()
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
                                        // Blocks of a section / columns of a colonnes block: same block maps, nested
                                        // (PageBlocks::normalize() validates them; a tree has no fixed config shape).
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
