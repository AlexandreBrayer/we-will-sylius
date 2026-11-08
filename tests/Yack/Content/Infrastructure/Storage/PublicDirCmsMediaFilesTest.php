<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Storage;

use App\Tests\Yack\Content\Application\CmsMediaImages;
use App\Yack\Content\Domain\CmsMediaFile;
use App\Yack\Content\Infrastructure\Storage\PublicDirCmsMediaFiles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Works in a public dir of its own under the system temporary dir, deleted after each test.
 *
 * @internal
 */
#[CoversClass(PublicDirCmsMediaFiles::class)]
final class PublicDirCmsMediaFilesTest extends TestCase
{
    private string $publicDir;

    #[\Override]
    protected function setUp(): void
    {
        $this->publicDir = sys_get_temp_dir() . '/yack-cms-media-' . bin2hex(random_bytes(6));
        mkdir($this->publicDir . '/yack/cms', 0o777, true);
        file_put_contents($this->publicDir . '/yack/cms/hero.jpg', 'visuel');
        file_put_contents($this->publicDir . '/index.php', '<?php');
    }

    #[\Override]
    protected function tearDown(): void
    {
        self::remove($this->publicDir);
    }

    public function testAFileIsStoredUnderARandomNameWithTheExtensionOfItsType(): void
    {
        $files = new PublicDirCmsMediaFiles($this->publicDir);
        $png = CmsMediaImages::png();

        $first = $files->store(CmsMediaFile::read($png));
        $second = $files->store(CmsMediaFile::read($png));

        $this->assertMatchesRegularExpression('#^media/yack-cms/[a-f0-9]{32}\.png$#D', $first);
        $this->assertNotSame($first, $second);
        $this->assertSame($png, file_get_contents($this->publicDir . '/' . $first));
        $this->assertStringEndsWith('.webp', $files->store(CmsMediaFile::read(CmsMediaImages::webp())));
    }

    public function testAnUploadedFileIsDeleted(): void
    {
        $files = new PublicDirCmsMediaFiles($this->publicDir);
        $path = $files->store(CmsMediaFile::read(CmsMediaImages::jpeg()));

        $files->delete($path);

        $this->assertFileDoesNotExist($this->publicDir . '/' . $path);
    }

    public function testNoOtherPathIsEverDeleted(): void
    {
        $files = new PublicDirCmsMediaFiles($this->publicDir);
        $files->store(CmsMediaFile::read(CmsMediaImages::jpeg()));
        symlink($this->publicDir . '/index.php', $this->publicDir . '/media/yack-cms/0123456789abcdef0123456789abcdef.jpg');

        foreach (['yack/cms/hero.jpg', 'index.php', '../index.php', 'media/yack-cms/../../index.php', 'media/yack-cms/0123456789abcdef0123456789abcdef.jpg', 'media/yack-cms/ffffffffffffffffffffffffffffffff.png'] as $path) {
            $files->delete($path);
        }

        $this->assertFileExists($this->publicDir . '/yack/cms/hero.jpg');
        $this->assertFileExists($this->publicDir . '/index.php');
        $this->assertCount(2, glob($this->publicDir . '/media/yack-cms/*') ?: []);
    }

    public function testAnUnwritableDirectoryIsAnError(): void
    {
        file_put_contents($this->publicDir . '/media', 'not a directory');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Dossier des médias impossible à créer');

        (new PublicDirCmsMediaFiles($this->publicDir))->store(CmsMediaFile::read(CmsMediaImages::png()));
    }

    private static function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ('.' !== $entry && '..' !== $entry) {
                self::remove($path . '/' . $entry);
            }
        }
        rmdir($path);
    }
}
