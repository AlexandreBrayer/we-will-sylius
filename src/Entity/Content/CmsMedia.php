<?php

declare(strict_types=1);

namespace App\Entity\Content;

use App\Yack\Content\Domain\CmsMediaFile;
use App\Yack\Content\Domain\CmsMediaRefused;
use Doctrine\ORM\Mapping as ORM;

/**
 * Image of the builder media library (Content\Domain\CmsMediaLibrary). `path` is relative to the Sylius
 * public dir, as the image props of the blocks store it: an uploaded file (`media/yack-cms/<random>.<ext>`,
 * CmsMediaFile::PATH_PATTERN) or a historical Yack visual (BlockType::IMAGES, registered by the fixtures;
 * its file belongs to the repository and is never deleted). Type, size in pixels and weight are those read
 * on the content when it was added; `originalName` is the cleaned name sent, data only.
 *
 * API: App\Yack\Content\Infrastructure\Api\CmsMediaView (/api/v2/admin/cms-media).
 */
#[ORM\Entity]
#[ORM\Table(name: 'yack_cms_media')]
#[ORM\Index(name: 'idx_yack_cms_media_created_at', columns: ['created_at'])]
class CmsMedia
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $path;

    #[ORM\Column(name: 'original_name', type: 'string', length: 255)]
    private string $originalName;

    #[ORM\Column(name: 'media_type', type: 'string', length: 32)]
    private string $mediaType;

    #[ORM\Column(type: 'integer')]
    private int $width;

    #[ORM\Column(type: 'integer')]
    private int $height;

    /** Weight in bytes. */
    #[ORM\Column(type: 'integer')]
    private int $size;

    /** Alternative text proposed by default when the image is chosen in a block. */
    #[ORM\Column(type: 'string', length: 255)]
    private string $alt;

    /** Name of the administrator who added it, null for the visuals registered by the fixtures. */
    #[ORM\Column(name: 'author_name', type: 'string', length: 255, nullable: true)]
    private ?string $authorName;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * @throws CmsMediaRefused alternative text too long
     */
    public function __construct(string $path, string $originalName, CmsMediaFile $file, string $alt = '', ?string $authorName = null, ?\DateTimeImmutable $createdAt = null)
    {
        $this->path = $path;
        $this->originalName = CmsMediaFile::cleanName($originalName);
        $this->mediaType = $file->mediaType;
        $this->width = $file->width;
        $this->height = $file->height;
        $this->size = $file->size();
        $this->alt = CmsMediaFile::cleanAlt($alt);
        $this->authorName = null === $authorName || '' === trim($authorName) ? null : mb_substr(trim($authorName), 0, 255);
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getMediaType(): string
    {
        return $this->mediaType;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getAlt(): string
    {
        return $this->alt;
    }

    /**
     * @throws CmsMediaRefused alternative text too long
     */
    public function describe(string $alt): void
    {
        $this->alt = CmsMediaFile::cleanAlt($alt);
    }

    public function getAuthorName(): ?string
    {
        return $this->authorName;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** True for a file sent to the library, false for a historical visual of the repository. */
    public function isUploaded(): bool
    {
        return CmsMediaFile::isUploadedPath($this->path);
    }
}
