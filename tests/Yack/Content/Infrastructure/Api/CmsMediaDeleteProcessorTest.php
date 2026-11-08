<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Delete;
use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsTemplate;
use App\Tests\Yack\Content\Application\InMemoryCmsMediaFiles;
use App\Tests\Yack\Content\Application\InMemoryCmsMediaLibrary;
use App\Yack\Content\Application\DeleteCmsMedia;
use App\Yack\Content\Domain\CmsPages;
use App\Yack\Content\Domain\CmsTemplates;
use App\Yack\Content\Domain\CmsTemplateType;
use App\Yack\Content\Infrastructure\Api\CmsMediaDeleteProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
#[CoversClass(CmsMediaDeleteProcessor::class)]
final class CmsMediaDeleteProcessorTest extends TestCase
{
    private const PHOTO = 'media/yack-cms/0123456789abcdef0123456789abcdef.jpg';

    public function testAnUnusedImageIsDeleted(): void
    {
        $library = new InMemoryCmsMediaLibrary();
        $files = new InMemoryCmsMediaFiles();
        $view = CmsMediaApi::provider($library)->view(CmsMediaApi::add($library, self::PHOTO, 'photo.jpg', '2026-11-08'));

        $this->assertNull(self::processor($library, $files, [])->process($view, new Delete(), ['id' => 1]));
        $this->assertSame([], $library->media);
        $this->assertSame([self::PHOTO], $files->deleted);
    }

    public function testAnImageInUseIsA409WithThePagesAndTemplates(): void
    {
        $library = new InMemoryCmsMediaLibrary();
        $view = CmsMediaApi::provider($library)->view(CmsMediaApi::add($library, self::PHOTO, 'photo.jpg', '2026-11-08'));
        $page = new CmsPage('accueil', 'Accueil');
        $page->setBlocks([['type' => 'hero', 'props' => ['image' => self::PHOTO]]]);

        $response = self::processor($library, new InMemoryCmsMediaFiles(), [$page])->process($view, new Delete());

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));
        $this->assertSame([
            'title' => 'Image utilisée',
            'status' => 409,
            'detail' => 'Cette image est utilisée par un contenu : page « Accueil ». Retirez-la de ces contenus avant de la supprimer.',
            'usages' => [['kind' => 'page', 'id' => 'accueil', 'title' => 'Accueil']],
        ], json_decode((string) $response->getContent(), true));
        $this->assertCount(1, $library->media);
    }

    public function testAnImageDeletedMeanwhileIsNotFound(): void
    {
        $library = new InMemoryCmsMediaLibrary();
        $view = CmsMediaApi::provider($library)->view(CmsMediaApi::add($library, self::PHOTO, 'photo.jpg', '2026-11-08'));
        $library->media = [];

        $this->expectException(NotFoundHttpException::class);

        self::processor($library, new InMemoryCmsMediaFiles(), [])->process($view, new Delete());
    }

    /**
     * @param list<CmsPage> $pages
     */
    private static function processor(InMemoryCmsMediaLibrary $library, InMemoryCmsMediaFiles $files, array $pages): CmsMediaDeleteProcessor
    {
        $pagesPort = new class($pages) implements CmsPages {
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
        };
        $templates = new class() implements CmsTemplates {
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
                return [];
            }

            public function add(CmsTemplate $template): void
            {
            }
        };

        return new CmsMediaDeleteProcessor($library, new DeleteCmsMedia($library, $files, $pagesPort, $templates));
    }
}
