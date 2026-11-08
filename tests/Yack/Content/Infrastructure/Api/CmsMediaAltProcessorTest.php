<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Put;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Tests\Yack\Content\Application\InMemoryCmsMediaLibrary;
use App\Yack\Content\Infrastructure\Api\CmsMediaAltProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
#[CoversClass(CmsMediaAltProcessor::class)]
final class CmsMediaAltProcessorTest extends TestCase
{
    public function testOnlyTheAlternativeTextChanges(): void
    {
        $library = new InMemoryCmsMediaLibrary();
        $media = CmsMediaApi::add($library, 'yack/cms/hero.jpg', 'hero.jpg', '2026-11-08');
        $view = CmsMediaApi::provider($library)->view($media);
        $view->alt = "  Photo d'un chantier\n ";
        $view->path = '../../etc/passwd';

        $saved = (new CmsMediaAltProcessor($library, CmsMediaApi::provider($library)))->process($view, new Put(), ['id' => 1]);

        $this->assertSame(["Photo d'un chantier", 'yack/cms/hero.jpg'], [$saved->alt, $saved->path]);
        $this->assertSame("Photo d'un chantier", $media->getAlt());
        $this->assertSame(1, $library->saves);
    }

    public function testATooLongAlternativeTextIsA422AndNothingIsSaved(): void
    {
        $library = new InMemoryCmsMediaLibrary();
        $media = CmsMediaApi::add($library, 'yack/cms/hero.jpg', 'hero.jpg', '2026-11-08');
        $view = CmsMediaApi::provider($library)->view($media);
        $view->alt = str_repeat('é', 256);

        try {
            (new CmsMediaAltProcessor($library, CmsMediaApi::provider($library)))->process($view, new Put());
            $this->fail('A too long text must be refused.');
        } catch (ValidationException $refused) {
            $this->assertSame('alt: Le texte alternatif fait au plus 255 caractères.', $refused->getMessage());
        }

        $this->assertSame('Texte', $media->getAlt());
        $this->assertSame(0, $library->saves);
    }

    public function testAnImageDeletedMeanwhileIsNotFound(): void
    {
        $library = new InMemoryCmsMediaLibrary();
        $view = CmsMediaApi::provider($library)->view(CmsMediaApi::add($library, 'yack/cms/hero.jpg', 'hero.jpg', '2026-11-08'));
        $library->media = [];

        $this->expectException(NotFoundHttpException::class);

        (new CmsMediaAltProcessor($library, CmsMediaApi::provider($library)))->process($view, new Put());
    }
}
