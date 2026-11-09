<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Application;

use App\Yack\Content\Application\UploadCmsMedia;
use App\Yack\Content\Domain\CmsMediaFile;
use App\Yack\Content\Domain\CmsMediaRefused;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(UploadCmsMedia::class)]
final class UploadCmsMediaTest extends TestCase
{
    private InMemoryCmsMediaLibrary $library;

    private InMemoryCmsMediaFiles $files;

    #[\Override]
    protected function setUp(): void
    {
        $this->library = new InMemoryCmsMediaLibrary();
        $this->files = new InMemoryCmsMediaFiles();
    }

    public function testAnImageIsStoredUnderARandomNameAndRecordedWithWhatWasReadOnItsContent(): void
    {
        $jpeg = CmsMediaImages::jpeg(640, 480);

        $media = $this->upload()($jpeg, '../../Photo chantier.png', "  Chantier\nde Marseille ", 'Sophie Martin');

        $this->assertMatchesRegularExpression(CmsMediaFile::PATH_PATTERN, $media->getPath());
        $this->assertStringEndsWith('.jpg', $media->getPath());
        $this->assertSame($jpeg, $this->files->files[$media->getPath()]);
        $this->assertSame(
            ['Photo chantier.png', 'image/jpeg', 640, 480, \strlen($jpeg), 'Chantier de Marseille', 'Sophie Martin', true],
            [$media->getOriginalName(), $media->getMediaType(), $media->getWidth(), $media->getHeight(), $media->getSize(), $media->getAlt(), $media->getAuthorName(), $media->isUploaded()],
        );
        $this->assertSame([$media], $this->library->search());
    }

    public function testARefusedFileIsNeitherStoredNorRecorded(): void
    {
        try {
            $this->upload()(CmsMediaImages::SVG, 'photo.jpg');
            $this->fail('An SVG must be refused.');
        } catch (CmsMediaRefused $refused) {
            $this->assertSame(CmsMediaRefused::svg()->getMessage(), $refused->getMessage());
        }

        $this->assertSame([], $this->files->files);
        $this->assertSame([], $this->library->media);
    }

    public function testATooLongAlternativeTextIsRefusedBeforeAnyFileIsStored(): void
    {
        $this->expectExceptionObject(CmsMediaRefused::altTooLong());

        try {
            $this->upload()(CmsMediaImages::png(), 'logo.png', str_repeat('a', 256));
        } finally {
            $this->assertSame([], $this->files->files);
        }
    }

    public function testTheStoredFileIsDeletedWhenTheRecordFails(): void
    {
        $this->library->failOnAdd = new \RuntimeException('base indisponible');

        try {
            $this->upload()(CmsMediaImages::webp(), 'vue.webp');
            $this->fail('The failure of the record must reach the caller.');
        } catch (\RuntimeException $failure) {
            $this->assertSame('base indisponible', $failure->getMessage());
        }

        $this->assertSame([], $this->files->files);
        $this->assertCount(1, $this->files->deleted);
    }

    public function testWithoutAuthorTheImageHasNone(): void
    {
        $this->assertNull($this->upload()(CmsMediaImages::png(), 'logo.png', '', '  ')->getAuthorName());
    }

    private function upload(): UploadCmsMedia
    {
        return new UploadCmsMedia($this->library, $this->files);
    }
}
