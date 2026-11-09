<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsRevision;
use App\Entity\Content\CmsTemplate;
use App\Yack\Content\Domain\CmsRevisionAuthor;
use App\Yack\Content\Domain\CmsRevisionSummary;
use App\Yack\Content\Domain\CmsRevisionTarget;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * History of the builder (JWT of a Sylius administrator, ROLE_API_ACCESS; 401 without it), read only:
 *  - GET /api/v2/admin/cms-pages/{slug}/revisions, GET /api/v2/admin/cms-templates/{id}/revisions: the
 *    revisions of the target, newest first, without the content (404 for an unknown target);
 *  - GET /api/v2/admin/cms-revisions/{id}: one revision with its `content`, under the names of the PUT
 *    of the target (App\Entity\Content\CmsRevision).
 * A revision is written by every successful save (Application\RecordCmsRevision). Restoring one is a
 * normal save of its content, which records a new revision.
 */
#[ApiResource(
    shortName: 'CmsRevision',
    operations: [
        new GetCollection(
            uriTemplate: '/admin/cms-pages/{slug}/revisions',
            uriVariables: ['slug' => new Link(fromClass: CmsPage::class, identifiers: ['slug'])],
            paginationEnabled: false,
            normalizationContext: ['groups' => ['cms_revision:list']],
            security: "is_granted('ROLE_API_ACCESS')",
            provider: CmsRevisionProvider::class,
            name: 'app_yack_api_admin_cms_page_revisions_get_collection',
        ),
        new GetCollection(
            uriTemplate: '/admin/cms-templates/{id}/revisions',
            uriVariables: ['id' => new Link(fromClass: CmsTemplate::class, identifiers: ['id'])],
            paginationEnabled: false,
            normalizationContext: ['groups' => ['cms_revision:list']],
            security: "is_granted('ROLE_API_ACCESS')",
            provider: CmsRevisionProvider::class,
            name: 'app_yack_api_admin_cms_template_revisions_get_collection',
        ),
        new Get(
            uriTemplate: '/admin/cms-revisions/{id}',
            normalizationContext: ['groups' => ['cms_revision:list', 'cms_revision:item']],
            security: "is_granted('ROLE_API_ACCESS')",
            provider: CmsRevisionProvider::class,
            name: 'app_yack_api_admin_cms_revision_get',
        ),
    ],
)]
final class CmsRevisionView
{
    /**
     * @param array{firstName: ?string, lastName: ?string, name: string}|null $author
     * @param array<string, mixed>|null                                       $content
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['cms_revision:list'])]
        public int $id,
        /** page | template */
        #[Groups(['cms_revision:list'])]
        public string $target,
        /** Slug of the page, or identifier of the template (as a string). */
        #[Groups(['cms_revision:list'])]
        public string $targetId,
        /** Date of the save, ISO 8601. */
        #[Groups(['cms_revision:list'])]
        public string $createdAt,
        /** Administrator who saved, null for the versions written by the fixtures or a command. */
        #[Groups(['cms_revision:list'])]
        public ?array $author,
        /** draft | published, at the time of the save. */
        #[Groups(['cms_revision:list'])]
        public string $status,
        /** Page title or template name at the time of the save. */
        #[Groups(['cms_revision:list'])]
        public string $title,
        #[Groups(['cms_revision:list'])]
        public int $blockCount,
        /** Page: title, metaTitle, metaDescription, status, blocks, grapesData; template: name, type, isDefault, status, blocks, grapesData. */
        #[Groups(['cms_revision:item'])]
        public ?array $content = null,
    ) {
    }

    public static function of(CmsRevision $revision): self
    {
        return new self(
            (int) $revision->getId(),
            $revision->target()->value,
            self::targetId($revision->getPage() ?? $revision->getTemplate()),
            $revision->getCreatedAt()->format(\DATE_ATOM),
            self::author($revision->author()),
            $revision->getStatus()->value,
            $revision->getTitle(),
            $revision->getBlockCount(),
            $revision->getContent(),
        );
    }

    public static function summary(CmsRevisionSummary $summary, CmsPage|CmsTemplate $target): self
    {
        return new self(
            $summary->id,
            ($target instanceof CmsPage ? CmsRevisionTarget::Page : CmsRevisionTarget::Template)->value,
            self::targetId($target),
            $summary->createdAt->format(\DATE_ATOM),
            self::author($summary->author),
            $summary->status->value,
            $summary->title,
            $summary->blockCount,
        );
    }

    private static function targetId(CmsPage|CmsTemplate|null $target): string
    {
        return match (true) {
            $target instanceof CmsPage => $target->getSlug(),
            $target instanceof CmsTemplate => (string) $target->getId(),
            default => '',
        };
    }

    /**
     * @return array{firstName: ?string, lastName: ?string, name: string}|null
     */
    private static function author(?CmsRevisionAuthor $author): ?array
    {
        return null === $author ? null : ['firstName' => $author->firstName, 'lastName' => $author->lastName, 'name' => $author->name];
    }
}
