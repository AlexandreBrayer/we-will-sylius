<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\User\AdminUser;
use App\Tests\Yack\Content\Application\CmsMediaImages;
use App\Tests\Yack\Content\Application\InMemoryCmsMediaFiles;
use App\Tests\Yack\Content\Application\InMemoryCmsMediaLibrary;
use App\Yack\Content\Application\UploadCmsMedia;
use App\Yack\Content\Infrastructure\Api\CmsMediaUploadProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Multipart request as Symfony builds it: PHP's temporary file in `files`, the other fields in `request`.
 *
 * @internal
 */
#[CoversClass(CmsMediaUploadProcessor::class)]
final class CmsMediaUploadProcessorTest extends TestCase
{
    private InMemoryCmsMediaLibrary $library;

    private InMemoryCmsMediaFiles $files;

    /** @var list<string> */
    private array $temporary = [];

    #[\Override]
    protected function setUp(): void
    {
        $this->library = new InMemoryCmsMediaLibrary();
        $this->files = new InMemoryCmsMediaFiles();
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->temporary as $path) {
            @unlink($path);
        }
    }

    public function testAnImageIsAddedWithTheAdministratorAsAuthorAndTheTemporaryFileIsDeleted(): void
    {
        $file = $this->uploaded(CmsMediaImages::jpeg(640, 480), '../Photo chantier.JPG');

        $view = $this->processor($this->admin('Sophie', 'Martin'))->process(null, new Post(), [], ['request' => self::request($file, ['alt' => 'Chantier'])]);

        $this->assertSame(['Photo chantier.JPG', 'image/jpeg', 640, 480, 'Chantier', 'Sophie Martin', true], [$view->name, $view->mediaType, $view->width, $view->height, $view->alt, $view->author, $view->uploaded]);
        $this->assertArrayHasKey($view->path, $this->files->files);
        $this->assertFileDoesNotExist($file->getPathname());
    }

    public function testAnAdministratorWithoutNameIsNamedByTheirEmailAndWithoutAdministratorThereIsNoAuthor(): void
    {
        $byEmail = $this->processor($this->admin(null, null))->process(null, new Post(), [], ['request' => self::request($this->uploaded(CmsMediaImages::png(), 'a.png'), ['alt' => ['tableau']])]);
        $anonymous = $this->processor(new TokenStorage())->process(null, new Post(), [], ['request' => self::request($this->uploaded(CmsMediaImages::png(), 'b.png'))]);

        $this->assertSame(['editeur@yack.test', ''], [$byEmail->author, $byEmail->alt]);
        $this->assertNull($anonymous->author);
    }

    #[DataProvider('provideRefusedFiles')]
    public function testARefusedFileIsA422OnTheFileField(string $bytes, string $name, ?int $error, string $message): void
    {
        $file = $this->uploaded($bytes, $name, $error);

        $this->expectRefusal('file', $message);

        try {
            $this->processor(new TokenStorage())->process(null, new Post(), [], ['request' => self::request($file)]);
        } finally {
            $this->assertFileDoesNotExist($file->getPathname());
            $this->assertSame([], $this->files->files);
        }
    }

    /**
     * @return \Iterator<string, array{string, string, ?int, string}>
     */
    public static function provideRefusedFiles(): \Iterator
    {
        yield 'svg' => [CmsMediaImages::SVG, 'dessin.svg', null, 'Les images SVG ne sont pas acceptées : envoyez un JPEG, un PNG ou un WebP.'];
        yield 'svg named jpg' => [CmsMediaImages::SVG, 'photo.jpg', null, 'Les images SVG ne sont pas acceptées : envoyez un JPEG, un PNG ou un WebP.'];
        yield 'pdf named jpg' => [CmsMediaImages::PDF, 'photo.jpg', null, 'Format non accepté : JPEG, PNG ou WebP uniquement.'];
        yield 'empty' => ['', 'vide.jpg', null, 'Le fichier est vide.'];
        yield 'over the limit of PHP' => ['x', 'gros.jpg', \UPLOAD_ERR_INI_SIZE, 'Fichier trop volumineux (20 Mo maximum).'];
        yield 'over the limit of the form' => ['x', 'gros.jpg', \UPLOAD_ERR_FORM_SIZE, 'Fichier trop volumineux (20 Mo maximum).'];
        yield 'no file in the field' => ['', '', \UPLOAD_ERR_NO_FILE, 'Choisissez un fichier image.'];
        yield 'interrupted upload' => ['x', 'coupe.jpg', \UPLOAD_ERR_PARTIAL, 'L\'envoi du fichier a échoué : réessayez.'];
    }

    public function testAFileOverTwentyMegabytesIsRefusedBeforeItIsRead(): void
    {
        $file = $this->uploaded(CmsMediaImages::jpeg() . str_repeat("\0", 20 * 1024 * 1024), 'gros.jpg');

        $this->expectRefusal('file', 'Fichier trop volumineux (20 Mo maximum).');

        try {
            $this->processor(new TokenStorage())->process(null, new Post(), [], ['request' => self::request($file)]);
        } finally {
            $this->assertFileDoesNotExist($file->getPathname());
        }
    }

    public function testWithoutFileTheFieldIsRequired(): void
    {
        $this->expectRefusal('file', 'Choisissez un fichier image.');

        $this->processor(new TokenStorage())->process(null, new Post(), [], ['request' => Request::create('/api/v2/admin/cms-media', 'POST', ['alt' => 'x'])]);
    }

    public function testWithoutRequestTheFieldIsRequired(): void
    {
        $this->expectRefusal('file', 'Choisissez un fichier image.');

        $this->processor(new TokenStorage())->process(null, new Post());
    }

    public function testATooLongAlternativeTextIsA422OnTheAltField(): void
    {
        $file = $this->uploaded(CmsMediaImages::png(), 'logo.png');

        $this->expectRefusal('alt', 'Le texte alternatif fait au plus 255 caractères.');

        try {
            $this->processor(new TokenStorage())->process(null, new Post(), [], ['request' => self::request($file, ['alt' => str_repeat('a', 256)])]);
        } finally {
            $this->assertFileDoesNotExist($file->getPathname());
        }
    }

    private function processor(TokenStorage $tokens): CmsMediaUploadProcessor
    {
        return new CmsMediaUploadProcessor(new UploadCmsMedia($this->library, $this->files), CmsMediaApi::provider($this->library), $tokens);
    }

    private function admin(?string $firstName, ?string $lastName): TokenStorage
    {
        $admin = new AdminUser();
        $admin->setEmail('editeur@yack.test');
        $admin->setFirstName($firstName);
        $admin->setLastName($lastName);
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken($admin, 'api_admin', ['ROLE_API_ACCESS']));

        return $tokens;
    }

    private function uploaded(string $bytes, string $name, ?int $error = null): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'yack-upload-');
        file_put_contents($path, $bytes);
        $this->temporary[] = $path;

        return new UploadedFile($path, $name, 'image/jpeg', $error, true);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private static function request(UploadedFile $file, array $fields = []): Request
    {
        return Request::create('/api/v2/admin/cms-media', 'POST', $fields, [], ['file' => $file]);
    }

    private function expectRefusal(string $field, string $message): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($field . ': ' . $message);
    }
}
