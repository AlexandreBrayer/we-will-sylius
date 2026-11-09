<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Tests\Yack\Content\Application\CmsMediaImages;
use App\Yack\Content\Domain\CmsMediaFile;
use App\Yack\Content\Domain\CmsMediaRefused;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CmsMediaFile::class)]
final class CmsMediaFileTest extends TestCase
{
    /**
     * @param array{string, string, int, int} $expected
     */
    #[DataProvider('provideAcceptedImages')]
    public function testJpegPngAndWebpAreReadOnTheirContent(string $bytes, array $expected): void
    {
        $file = CmsMediaFile::read($bytes);

        $this->assertSame($expected, [$file->mediaType, $file->extension(), $file->width, $file->height]);
        $this->assertSame(\strlen($bytes), $file->size());
        $this->assertSame($bytes, $file->bytes);
    }

    /**
     * @return \Iterator<string, array{string, array{string, string, int, int}}>
     */
    public static function provideAcceptedImages(): \Iterator
    {
        yield 'jpeg' => [CmsMediaImages::jpeg(64, 48), ['image/jpeg', 'jpg', 64, 48]];
        yield 'png' => [CmsMediaImages::png(30, 20), ['image/png', 'png', 30, 20]];
        yield 'webp' => [CmsMediaImages::webp(12, 9), ['image/webp', 'webp', 12, 9]];
    }

    #[DataProvider('provideRefusedFiles')]
    public function testOtherFilesAreRefusedWithAFrenchMessage(string $bytes, string $message): void
    {
        $this->expectException(CmsMediaRefused::class);
        $this->expectExceptionMessage($message);

        CmsMediaFile::read($bytes);
    }

    /**
     * @return \Iterator<string, array{string, string}>
     */
    public static function provideRefusedFiles(): \Iterator
    {
        yield 'empty' => ['', 'Le fichier est vide.'];
        yield 'svg' => [CmsMediaImages::SVG, 'Les images SVG ne sont pas acceptées : envoyez un JPEG, un PNG ou un WebP.'];
        yield 'svg without xml declaration' => ['<svg xmlns="http://www.w3.org/2000/svg"><circle r="4"/></svg>', 'Les images SVG ne sont pas acceptées'];
        yield 'pdf' => [CmsMediaImages::PDF, 'Format non accepté : JPEG, PNG ou WebP uniquement.'];
        yield 'html' => ['<!DOCTYPE html><html><body><script>alert(1)</script></body></html>', 'Format non accepté'];
        yield 'gif' => [CmsMediaImages::gif(), 'Format non accepté'];
        yield 'jpeg signature, no image' => ["\xFF\xD8\xFF\xE0" . str_repeat('x', 64), 'Image illisible : le fichier est peut-être abîmé.'];
        yield 'too many pixels' => [CmsMediaImages::pngHeader(8000, 6000), 'Image trop grande (40 mégapixels au plus) : réduisez-la avant de l\'envoyer.'];
    }

    public function testAFileOverTwentyMegabytesIsRefused(): void
    {
        $this->expectExceptionObject(CmsMediaRefused::tooLarge());

        CmsMediaFile::read(CmsMediaImages::jpeg() . str_repeat("\0", CmsMediaFile::MAX_BYTES));
    }

    public function testAnImageOfExactlyTwentyMegabytesIsAccepted(): void
    {
        $jpeg = CmsMediaImages::jpeg();
        $file = CmsMediaFile::read($jpeg . str_repeat("\0", CmsMediaFile::MAX_BYTES - \strlen($jpeg)));

        $this->assertSame(20 * 1024 * 1024, $file->size());
    }

    public function testFortyMegapixelsIsTheLimit(): void
    {
        $this->assertSame([8000, 5000], [CmsMediaFile::read(CmsMediaImages::pngHeader(8000, 5000))->width, CmsMediaFile::read(CmsMediaImages::pngHeader(8000, 5000))->height]);
    }

    #[DataProvider('provideUploadedPaths')]
    public function testUploadedPathsHaveARandomNameAndAVerifiedExtension(string $path, bool $uploaded): void
    {
        $this->assertSame($uploaded, CmsMediaFile::isUploadedPath($path));
    }

    /**
     * @return \Iterator<string, array{string, bool}>
     */
    public static function provideUploadedPaths(): \Iterator
    {
        yield 'jpg' => ['media/yack-cms/0123456789abcdef0123456789abcdef.jpg', true];
        yield 'webp' => ['media/yack-cms/0123456789abcdef0123456789abcdef.webp', true];
        yield 'historical visual' => ['yack/cms/hero.jpg', false];
        yield 'leading slash' => ['/media/yack-cms/0123456789abcdef0123456789abcdef.jpg', false];
        yield 'upper case' => ['media/yack-cms/0123456789ABCDEF0123456789abcdef.jpg', false];
        yield 'short name' => ['media/yack-cms/0123.jpg', false];
        yield 'svg' => ['media/yack-cms/0123456789abcdef0123456789abcdef.svg', false];
        yield 'traversal' => ['media/yack-cms/../../index.php', false];
        yield 'trailing text' => ["media/yack-cms/0123456789abcdef0123456789abcdef.jpg\n", false];
    }

    #[DataProvider('provideNames')]
    public function testTheNameSentIsCleanedAndNeverAPath(string $sent, string $kept): void
    {
        $this->assertSame($kept, CmsMediaFile::cleanName($sent));
    }

    /**
     * @return \Iterator<string, array{string, string}>
     */
    public static function provideNames(): \Iterator
    {
        yield 'plain' => ['Photo chantier été.JPG', 'Photo chantier été.JPG'];
        yield 'relative path' => ['../../etc/passwd', 'passwd'];
        yield 'windows path' => ['C:\\Users\\yack\\photo.png', 'photo.png'];
        yield 'control characters' => ["photo\x00\x1b[31m\nrouge.png", 'photo [31m rouge.png'];
        yield 'spaces' => ["  vue   du\tsite.webp  ", 'vue du site.webp'];
        yield 'nothing left' => ['../', 'image'];
        yield 'dots' => ['..', 'image'];
        yield 'empty' => ['', 'image'];
        yield 'not utf-8' => ["caf\xE9.jpg", 'café.jpg'];
        yield 'too long' => [str_repeat('é', 300) . '.jpg', str_repeat('é', 255)];
    }

    public function testTheAlternativeTextIsOneTrimmedLine(): void
    {
        $this->assertSame('Chantier de Marseille', CmsMediaFile::cleanAlt("  Chantier\nde\x07 Marseille  "));
        $this->assertSame(str_repeat('é', 255), CmsMediaFile::cleanAlt(str_repeat('é', 255)));
    }

    public function testAnAlternativeTextOverTheLimitIsRefused(): void
    {
        $this->expectExceptionObject(CmsMediaRefused::altTooLong());

        CmsMediaFile::cleanAlt(str_repeat('é', 256));
    }
}
