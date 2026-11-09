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
use App\Entity\Content\CmsSection;
use App\Yack\Content\Domain\PageBlocks;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Reusable sections of the builder, « Mes sections » (D12; JWT of a Sylius administrator, ROLE_API_ACCESS;
 * 401 without it), shared by the administrators:
 *  - GET /api/v2/admin/cms-sections: every section with its blocks, by name;
 *  - GET /api/v2/admin/cms-sections/{id};
 *  - POST /api/v2/admin/cms-sections {"name": "...", "blocks": [{type, props, style}]}: 201, or 422 on `name`
 *    or on `blocks` (validated as the blocks of a page, free blocks only, builder level of the author:
 *    Application\CreateCmsSection);
 *  - PUT /api/v2/admin/cms-sections/{id} {"name": "..."}: renames it (its blocks never change), 200 or 422;
 *  - DELETE /api/v2/admin/cms-sections/{id}: 204. No page changes: an inserted section is a copy.
 */
#[ApiResource(
    shortName: 'CmsSection',
    operations: [
        new GetCollection(
            uriTemplate: '/admin/cms-sections',
            paginationEnabled: false,
            normalizationContext: ['groups' => ['cms_section:read']],
            security: "is_granted('ROLE_API_ACCESS')",
            provider: CmsSectionProvider::class,
            name: 'app_yack_api_admin_cms_section_get_collection',
        ),
        new Get(
            uriTemplate: '/admin/cms-sections/{id}',
            normalizationContext: ['groups' => ['cms_section:read']],
            security: "is_granted('ROLE_API_ACCESS')",
            provider: CmsSectionProvider::class,
            name: 'app_yack_api_admin_cms_section_get',
        ),
        new Post(
            uriTemplate: '/admin/cms-sections',
            status: 201,
            normalizationContext: ['groups' => ['cms_section:read']],
            denormalizationContext: ['groups' => ['cms_section:create']],
            security: "is_granted('ROLE_API_ACCESS')",
            input: CmsSectionInput::class,
            validate: false,
            processor: CmsSectionCreateProcessor::class,
            name: 'app_yack_api_admin_cms_section_post',
        ),
        new Put(
            uriTemplate: '/admin/cms-sections/{id}',
            normalizationContext: ['groups' => ['cms_section:read']],
            denormalizationContext: ['groups' => ['cms_section:rename']],
            security: "is_granted('ROLE_API_ACCESS')",
            validate: false,
            provider: CmsSectionProvider::class,
            processor: CmsSectionRenameProcessor::class,
            name: 'app_yack_api_admin_cms_section_put',
        ),
        new Delete(
            uriTemplate: '/admin/cms-sections/{id}',
            security: "is_granted('ROLE_API_ACCESS')",
            provider: CmsSectionProvider::class,
            processor: CmsSectionDeleteProcessor::class,
            name: 'app_yack_api_admin_cms_section_delete',
        ),
    ],
)]
final class CmsSectionView
{
    /**
     * @param list<array{type: string, props: array<string, string>, style: array<string, string>}> $blocks
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['cms_section:read'])]
        public int $id,
        /** One line, 120 characters at most. */
        #[Groups(['cms_section:read', 'cms_section:rename'])]
        public string $name,
        /** Free blocks `{type, props, style}`, normalized as the blocks of a page (an empty style is `[]`, as in the pages). */
        #[Groups(['cms_section:read'])]
        public array $blocks,
        /** Every block, containers and their blocks included (PageBlocks::count()). */
        #[Groups(['cms_section:read'])]
        public int $blockCount,
        /** Administrator who saved it, null when saved without one. */
        #[Groups(['cms_section:read'])]
        public ?string $author,
        /** ISO 8601. */
        #[Groups(['cms_section:read'])]
        public string $createdAt,
        /** ISO 8601, last renaming (the creation date when never renamed). */
        #[Groups(['cms_section:read'])]
        public string $updatedAt,
    ) {
    }

    public static function of(CmsSection $section): self
    {
        $blocks = $section->getBlocks();

        return new self(
            (int) $section->getId(),
            $section->getName(),
            $blocks,
            PageBlocks::count($blocks),
            $section->getAuthorName(),
            $section->getCreatedAt()->format(\DATE_ATOM),
            $section->getUpdatedAt()->format(\DATE_ATOM),
        );
    }
}
