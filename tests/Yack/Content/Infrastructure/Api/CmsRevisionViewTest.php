<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsRevision;
use App\Entity\Content\CmsTemplate;
use App\Tests\Yack\Content\Application\InMemoryCmsRevisions;
use App\Yack\Content\Domain\CmsPageStatus;
use App\Yack\Content\Domain\CmsRevisionAuthor;
use App\Yack\Content\Domain\CmsRevisionSummary;
use App\Yack\Content\Domain\CmsTemplateType;
use App\Yack\Content\Infrastructure\Api\CmsRevisionView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 */
#[CoversClass(CmsRevisionView::class)]
final class CmsRevisionViewTest extends TestCase
{
    public function testTheListGroupGivesALineOfTheHistoryWithoutContent(): void
    {
        $page = new CmsPage('accueil', 'Accueil');
        $summary = new CmsRevisionSummary(7, new \DateTimeImmutable('2026-11-08T14:05:00+00:00'), new CmsRevisionAuthor('Sophie', 'Martin', 'Sophie Martin'), CmsPageStatus::Published, 'Accueil', 9);

        $this->assertSame([
            'id' => 7,
            'target' => 'page',
            'targetId' => 'accueil',
            'createdAt' => '2026-11-08T14:05:00+00:00',
            'author' => ['firstName' => 'Sophie', 'lastName' => 'Martin', 'name' => 'Sophie Martin'],
            'status' => 'published',
            'title' => 'Accueil',
            'blockCount' => 9,
        ], self::normalize(CmsRevisionView::summary($summary, $page), ['cms_revision:list']));
    }

    public function testTheItemGroupAddsTheContent(): void
    {
        $template = new CmsTemplate(CmsTemplateType::Product, 'Fiche');
        InMemoryCmsRevisions::identify($template, 3);
        $template->setBlocks([['type' => 'produit-achat', 'props' => [], 'style' => ['fond' => '#ff7700']]]);
        $revision = CmsRevision::ofTemplate($template, null, new \DateTimeImmutable('2026-11-08T14:05:00+00:00'));
        InMemoryCmsRevisions::identify($revision, 12);

        $normalized = self::normalize(CmsRevisionView::of($revision), ['cms_revision:list', 'cms_revision:item']);

        $this->assertSame(['id', 'target', 'targetId', 'createdAt', 'author', 'status', 'title', 'blockCount', 'content'], array_keys($normalized));
        $this->assertSame('template', $normalized['target']);
        $this->assertSame('3', $normalized['targetId']);
        $this->assertNull($normalized['author']);
        $this->assertSame('draft', $normalized['status']);
        $this->assertSame($revision->getContent(), $normalized['content']);
    }

    public function testAPageRevisionNamesItsPageBySlug(): void
    {
        $revision = CmsRevision::ofPage(new CmsPage('offre', 'Offre'), null);

        $this->assertSame('offre', CmsRevisionView::of($revision)->targetId);
        $this->assertSame('page', CmsRevisionView::of($revision)->target);
    }

    /**
     * @param list<string> $groups
     *
     * @return array<string, mixed>
     */
    private static function normalize(CmsRevisionView $view, array $groups): array
    {
        $serializer = new Serializer([new ObjectNormalizer(new ClassMetadataFactory(new AttributeLoader()), propertyTypeExtractor: new ReflectionExtractor())]);
        /** @var array<string, mixed> $normalized */
        $normalized = $serializer->normalize($view, null, ['groups' => $groups]);

        return $normalized;
    }
}
