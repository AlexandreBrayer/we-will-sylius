<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Application;

use App\Entity\Content\CmsMedia;
use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsTemplate;
use App\Yack\Content\Application\DeleteCmsMedia;
use App\Yack\Content\Domain\CmsMediaFile;
use App\Yack\Content\Domain\CmsMediaInUse;
use App\Yack\Content\Domain\CmsMediaUsage;
use App\Yack\Content\Domain\CmsPages;
use App\Yack\Content\Domain\CmsTemplates;
use App\Yack\Content\Domain\CmsTemplateType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DeleteCmsMedia::class)]
final class DeleteCmsMediaTest extends TestCase
{
    private const PHOTO = 'media/yack-cms/0123456789abcdef0123456789abcdef.jpg';

    private InMemoryCmsMediaLibrary $library;

    private InMemoryCmsMediaFiles $files;

    #[\Override]
    protected function setUp(): void
    {
        $this->library = new InMemoryCmsMediaLibrary();
        $this->files = new InMemoryCmsMediaFiles();
    }

    public function testAnUnusedUploadedImageLeavesTheLibraryWithItsFile(): void
    {
        $media = $this->media(self::PHOTO);

        $this->delete([self::page('accueil', 'Accueil', 'yack/cms/hero.jpg')], [])($media);

        $this->assertSame([], $this->library->media);
        $this->assertSame([self::PHOTO], $this->files->deleted);
    }

    public function testAnImageShownByPagesOrTemplatesIsKeptAndTheyAreListed(): void
    {
        $media = $this->media(self::PHOTO);
        $template = self::template('Fiche standard', self::PHOTO);
        $delete = $this->delete(
            [self::page('accueil', 'Accueil', self::PHOTO), self::page('sav', 'SAV', 'yack/cms/hero.jpg'), self::page('brouillon', 'Offre (brouillon)', self::PHOTO)],
            [$template, self::template('Autre', 'yack/cms/console.png')],
        );

        try {
            $delete($media);
            $this->fail('An image in use must not be deleted.');
        } catch (CmsMediaInUse $inUse) {
            $this->assertSame(
                [['page', 'accueil', 'Accueil'], ['page', 'brouillon', 'Offre (brouillon)'], ['template', '7', 'Fiche standard']],
                array_map(static fn (CmsMediaUsage $usage): array => [$usage->kind, $usage->id, $usage->title], $inUse->usages),
            );
        }

        $this->assertCount(1, $this->library->media);
        $this->assertSame([], $this->files->deleted);
    }

    public function testAHistoricalVisualLeavesTheLibraryButKeepsItsFile(): void
    {
        $media = $this->media('yack/cms/console.png');

        $this->delete([self::page('accueil', 'Accueil', 'yack/cms/hero.jpg')], [])($media);

        $this->assertSame([], $this->library->media);
        $this->assertSame([], $this->files->deleted);
    }

    public function testAHistoricalVisualShownByAPageIsKept(): void
    {
        $media = $this->media('yack/cms/hero.jpg');

        $this->expectException(CmsMediaInUse::class);
        $this->expectExceptionMessage('page « Accueil »');

        $this->delete([self::page('accueil', 'Accueil', 'yack/cms/hero.jpg')], [])($media);
    }

    private function media(string $path): CmsMedia
    {
        $media = new CmsMedia($path, basename($path), CmsMediaFile::read(CmsMediaImages::jpeg()));
        $this->library->add($media);

        return $media;
    }

    /**
     * @param list<CmsPage>     $pages
     * @param list<CmsTemplate> $templates
     */
    private function delete(array $pages, array $templates): DeleteCmsMedia
    {
        return new DeleteCmsMedia($this->library, $this->files, new class($pages) implements CmsPages {
            /** @param list<CmsPage> $pages */
            public function __construct(private readonly array $pages)
            {
            }

            public function findPublished(string $slug): ?CmsPage
            {
                return null;
            }

            public function published(): array
            {
                return [];
            }

            public function all(): array
            {
                return $this->pages;
            }
        }, new class($templates) implements CmsTemplates {
            /** @param list<CmsTemplate> $templates */
            public function __construct(private readonly array $templates)
            {
            }

            public function find(int $id): ?CmsTemplate
            {
                return null;
            }

            public function findDefault(CmsTemplateType $type): ?CmsTemplate
            {
                return null;
            }

            public function published(CmsTemplateType $type): array
            {
                return [];
            }

            public function all(): array
            {
                return $this->templates;
            }

            public function add(CmsTemplate $template): void
            {
            }
        });
    }

    private static function page(string $slug, string $title, string $image): CmsPage
    {
        $page = new CmsPage($slug, $title);
        $page->setBlocks([['type' => 'faq', 'props' => []], ['type' => 'texte-image', 'props' => ['image' => $image]]]);

        return $page;
    }

    private static function template(string $name, string $image): CmsTemplate
    {
        $template = new CmsTemplate(CmsTemplateType::Product, $name);
        $template->setBlocks([['type' => 'produit-achat', 'props' => []], ['type' => 'tuiles', 'props' => ['image4' => $image]]]);
        (new \ReflectionProperty(CmsTemplate::class, 'id'))->setValue($template, 7);

        return $template;
    }
}
