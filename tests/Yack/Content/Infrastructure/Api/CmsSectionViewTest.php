<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use App\Entity\Content\CmsSection;
use App\Yack\Content\Infrastructure\Api\CmsSectionView;
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
#[CoversClass(CmsSectionView::class)]
final class CmsSectionViewTest extends TestCase
{
    public function testTheViewGivesTheBlocksTheirCountTheAuthorAndTheDates(): void
    {
        $blocks = [['type' => 'cta', 'props' => ['title' => 'Appel'], 'style' => []], ['type' => 'faq', 'props' => [], 'style' => ['fond' => '#ff7700']]];
        $section = new CmsSection('Bandeau', $blocks, 'Sophie Martin', new \DateTimeImmutable('2026-11-08T14:05:00+00:00'));
        (new \ReflectionProperty(CmsSection::class, 'id'))->setValue($section, 7);
        $section->rename('Bandeau promo', new \DateTimeImmutable('2026-11-08T15:00:00+00:00'));

        $view = CmsSectionView::of($section);

        $this->assertSame(
            [7, 'Bandeau promo', $blocks, 2, 'Sophie Martin', '2026-11-08T14:05:00+00:00', '2026-11-08T15:00:00+00:00'],
            [$view->id, $view->name, $view->blocks, $view->blockCount, $view->author, $view->createdAt, $view->updatedAt],
        );
    }

    public function testOnlyTheNameIsWrittenByARenaming(): void
    {
        $serializer = new Serializer([new ObjectNormalizer(new ClassMetadataFactory(new AttributeLoader()), propertyTypeExtractor: new ReflectionExtractor())]);
        $view = CmsSectionView::of(self::section());

        $serializer->denormalize(['id' => 99, 'name' => 'Nouveau nom', 'blocks' => [], 'blockCount' => 9, 'author' => 'x'], CmsSectionView::class, null, ['groups' => ['cms_section:rename'], 'object_to_populate' => $view]);

        $this->assertSame([1, 'Nouveau nom', 1, 'Sophie Martin'], [$view->id, $view->name, $view->blockCount, $view->author]);
        $read = $serializer->normalize($view, null, ['groups' => ['cms_section:read']]);
        $this->assertIsArray($read);
        $this->assertSame(['id', 'name', 'blocks', 'blockCount', 'author', 'createdAt', 'updatedAt'], array_keys($read));
    }

    private static function section(): CmsSection
    {
        $section = new CmsSection('Bandeau', [['type' => 'cta', 'props' => [], 'style' => []]], 'Sophie Martin');
        (new \ReflectionProperty(CmsSection::class, 'id'))->setValue($section, 1);

        return $section;
    }
}
