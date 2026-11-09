<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\Content\CmsMedia;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Media library of the builder (JWT of a Sylius administrator, ROLE_API_ACCESS; 401 without it):
 *  - GET /api/v2/admin/cms-media[?q=nom]: images, newest first, filtered on the name sent (case ignored);
 *  - GET /api/v2/admin/cms-media/{id};
 *  - POST /api/v2/admin/cms-media (multipart/form-data, field `file`, optional `alt`): 201, or 422 on `file`
 *    (empty, over 20 Mo, SVG, type read on the content other than JPEG, PNG, WebP, unreadable image) or on `alt`;
 *  - PUT /api/v2/admin/cms-media/{id} {"alt": "..."}: alternative text, 200 or 422 on `alt`;
 *  - DELETE /api/v2/admin/cms-media/{id}: 204 (file deleted), or 409 with `detail` and `usages` (pages,
 *    templates and reusable sections that show the image).
 * `path` is the value to store in an image prop of a block; `url` and `thumbnail` are paths of the Sylius
 * host (nginx sends /media/ to Sylius).
 */
#[ApiResource(
    shortName: 'CmsMedia',
    operations: [
        new GetCollection(
            uriTemplate: '/admin/cms-media',
            paginationEnabled: false,
            normalizationContext: ['groups' => ['cms_media:read']],
            security: "is_granted('ROLE_API_ACCESS')",
            provider: CmsMediaProvider::class,
            name: 'app_yack_api_admin_cms_media_get_collection',
        ),
        new Get(
            uriTemplate: '/admin/cms-media/{id}',
            normalizationContext: ['groups' => ['cms_media:read']],
            security: "is_granted('ROLE_API_ACCESS')",
            provider: CmsMediaProvider::class,
            name: 'app_yack_api_admin_cms_media_get',
        ),
        new Post(
            uriTemplate: '/admin/cms-media',
            status: 201,
            inputFormats: ['multipart' => ['multipart/form-data']],
            normalizationContext: ['groups' => ['cms_media:read']],
            security: "is_granted('ROLE_API_ACCESS')",
            input: false,
            // read: the provider answers null, which API Platform keeps as the request data; with read: false
            // no data is set and the placeholder controller of API Platform fails (500).
            read: true,
            deserialize: false,
            validate: false,
            provider: CmsMediaProvider::class,
            processor: CmsMediaUploadProcessor::class,
            name: 'app_yack_api_admin_cms_media_post',
        ),
        new Put(
            uriTemplate: '/admin/cms-media/{id}',
            normalizationContext: ['groups' => ['cms_media:read']],
            denormalizationContext: ['groups' => ['cms_media:write']],
            security: "is_granted('ROLE_API_ACCESS')",
            validate: false,
            provider: CmsMediaProvider::class,
            processor: CmsMediaAltProcessor::class,
            name: 'app_yack_api_admin_cms_media_put',
        ),
        new Delete(
            uriTemplate: '/admin/cms-media/{id}',
            security: "is_granted('ROLE_API_ACCESS')",
            provider: CmsMediaProvider::class,
            processor: CmsMediaDeleteProcessor::class,
            name: 'app_yack_api_admin_cms_media_delete',
        ),
    ],
)]
final class CmsMediaView
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['cms_media:read'])]
        public int $id,
        /** Value of an image prop: `media/yack-cms/<random>.<ext>`, or `yack/...` for a historical visual. */
        #[Groups(['cms_media:read'])]
        public string $path,
        /** Full image, `/` + path. */
        #[Groups(['cms_media:read'])]
        public string $url,
        /** Thumbnail (LiipImagine filter yack_cms_media_thumb, WebP, 400 px at most). */
        #[Groups(['cms_media:read'])]
        public string $thumbnail,
        /** Name of the file sent (cleaned), or of the historical visual. */
        #[Groups(['cms_media:read'])]
        public string $name,
        /** image/jpeg | image/png | image/webp, read on the content. */
        #[Groups(['cms_media:read'])]
        public string $mediaType,
        #[Groups(['cms_media:read'])]
        public int $width,
        #[Groups(['cms_media:read'])]
        public int $height,
        /** Weight in bytes. */
        #[Groups(['cms_media:read'])]
        public int $size,
        /** Alternative text proposed by default, '' when none (255 characters at most). */
        #[Groups(['cms_media:read', 'cms_media:write'])]
        public string $alt,
        /** Administrator who sent it, null for a historical visual. */
        #[Groups(['cms_media:read'])]
        public ?string $author,
        /** ISO 8601. */
        #[Groups(['cms_media:read'])]
        public string $createdAt,
        /** False for a historical visual: deleting it takes it out of the library, its file stays. */
        #[Groups(['cms_media:read'])]
        public bool $uploaded,
    ) {
    }

    public static function of(CmsMedia $media, string $thumbnail): self
    {
        return new self(
            (int) $media->getId(),
            $media->getPath(),
            '/' . $media->getPath(),
            $thumbnail,
            $media->getOriginalName(),
            $media->getMediaType(),
            $media->getWidth(),
            $media->getHeight(),
            $media->getSize(),
            $media->getAlt(),
            $media->getAuthorName(),
            $media->getCreatedAt()->format(\DATE_ATOM),
            $media->isUploaded(),
        );
    }
}
