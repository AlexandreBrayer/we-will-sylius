<?php

declare(strict_types=1);

namespace App\Entity\Content;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Yack\Content\Domain\CmsPageStatus;
use App\Yack\Content\Domain\InvalidPageBlocks;
use App\Yack\Content\Domain\PageBlocks;
use App\Yack\Content\Infrastructure\Api\PublishedCmsPageProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * CMS page built with the SvelteKit builder (front/): ordered Yack blocks + raw GrapesJS
 * project data (editor state only, never rendered).
 *
 * API (prefix /api/v2):
 *  - admin (JWT of a Sylius administrator, ROLE_API_ACCESS): CRUD on /admin/cms-pages[/{slug}]
 *  - shop (public): /shop/cms-pages[/{slug}], published pages only
 */
#[ORM\Entity]
#[ORM\Table(name: 'yack_cms_page')]
#[UniqueEntity(fields: ['slug'], message: 'Une page utilise déjà cette adresse (slug).')]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/cms-pages',
            order: ['updatedAt' => 'DESC'],
            normalizationContext: ['groups' => ['yack:cms:admin:index']],
            security: "is_granted('ROLE_API_ACCESS')",
            name: 'app_yack_api_admin_cms_page_get_collection',
        ),
        new Get(
            uriTemplate: '/admin/cms-pages/{slug}',
            normalizationContext: ['groups' => ['yack:cms:admin:index', 'yack:cms:admin:show']],
            security: "is_granted('ROLE_API_ACCESS')",
            name: 'app_yack_api_admin_cms_page_get',
        ),
        new Post(
            uriTemplate: '/admin/cms-pages',
            normalizationContext: ['groups' => ['yack:cms:admin:index', 'yack:cms:admin:show']],
            denormalizationContext: ['groups' => ['yack:cms:admin:create', 'yack:cms:admin:update']],
            security: "is_granted('ROLE_API_ACCESS')",
            name: 'app_yack_api_admin_cms_page_post',
        ),
        new Put(
            uriTemplate: '/admin/cms-pages/{slug}',
            normalizationContext: ['groups' => ['yack:cms:admin:index', 'yack:cms:admin:show']],
            denormalizationContext: ['groups' => ['yack:cms:admin:update']],
            security: "is_granted('ROLE_API_ACCESS')",
            name: 'app_yack_api_admin_cms_page_put',
        ),
        new Delete(
            uriTemplate: '/admin/cms-pages/{slug}',
            security: "is_granted('ROLE_API_ACCESS')",
            name: 'app_yack_api_admin_cms_page_delete',
        ),
        new GetCollection(
            uriTemplate: '/shop/cms-pages',
            normalizationContext: ['groups' => ['yack:cms:shop:index']],
            provider: PublishedCmsPageProvider::class,
            name: 'app_yack_api_shop_cms_page_get_collection',
        ),
        new Get(
            uriTemplate: '/shop/cms-pages/{slug}',
            normalizationContext: ['groups' => ['yack:cms:shop:index', 'yack:cms:shop:show']],
            provider: PublishedCmsPageProvider::class,
            name: 'app_yack_api_shop_cms_page_get',
        ),
    ],
    paginationEnabled: false,
)]
class CmsPage
{
    /**
     * Paths routed by nginx to Sylius, or taken by a route of the SvelteKit front (front/src/routes);
     * "templates": /cms-admin/builder/templates (template list) would hide the builder of such a page.
     */
    public const RESERVED_SLUGS = [
        'admin', 'api', 'media', 'bundles', 'build', 'cms-admin',
        'produits', 'pieces', 'produit', 'recherche', 'panier', 'commande', 'compte', 'connexion', 'deconnexion',
        'inscription', 'activation', 'mot-de-passe', 'sav', 'trouver-un-installateur', 'contact', 'templates',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[ApiProperty(identifier: false)]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    #[ApiProperty(identifier: true)]
    #[Groups(['yack:cms:admin:index', 'yack:cms:admin:create', 'yack:cms:shop:index'])]
    #[Assert\NotBlank(message: "L'adresse (slug) est obligatoire.")]
    #[Assert\Length(max: 100)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: "L'adresse (slug) ne contient que des minuscules, chiffres et tirets (ex. offre-printemps-pac).")]
    #[Assert\Choice(choices: self::RESERVED_SLUGS, match: false, message: 'Cette adresse est réservée.')]
    private string $slug;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['yack:cms:admin:index', 'yack:cms:admin:update', 'yack:cms:shop:index'])]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(max: 255)]
    private string $title;

    #[ORM\Column(name: 'meta_title', type: 'string', length: 255, nullable: true)]
    #[Groups(['yack:cms:admin:show', 'yack:cms:admin:update', 'yack:cms:shop:show'])]
    #[Assert\Length(max: 255)]
    private ?string $metaTitle = null;

    #[ORM\Column(name: 'meta_description', type: 'string', length: 500, nullable: true)]
    #[Groups(['yack:cms:admin:show', 'yack:cms:admin:update', 'yack:cms:shop:show'])]
    #[Assert\Length(max: 500)]
    private ?string $metaDescription = null;

    #[ORM\Column(type: 'string', length: 16, enumType: CmsPageStatus::class)]
    #[Groups(['yack:cms:admin:index', 'yack:cms:admin:update'])]
    private CmsPageStatus $status = CmsPageStatus::Draft;

    /** @var list<array{type: string, props: array<string, string>, style?: array<string, string>}> */
    #[ORM\Column(type: 'json')]
    #[Groups(['yack:cms:admin:show', 'yack:cms:admin:update', 'yack:cms:shop:show'])]
    private array $blocks = [];

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'grapes_data', type: 'json', nullable: true)]
    #[Groups(['yack:cms:admin:show', 'yack:cms:admin:update'])]
    private ?array $grapesData = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    #[Groups(['yack:cms:admin:index', 'yack:cms:shop:index'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $slug = '', string $title = '')
    {
        $this->slug = $slug;
        $this->title = $title;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
        $this->touch();
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): void
    {
        $this->metaTitle = '' === $metaTitle ? null : $metaTitle;
        $this->touch();
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): void
    {
        $this->metaDescription = '' === $metaDescription ? null : $metaDescription;
        $this->touch();
    }

    public function getStatus(): CmsPageStatus
    {
        return $this->status;
    }

    public function setStatus(CmsPageStatus $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    public function isPublished(): bool
    {
        return CmsPageStatus::Published === $this->status;
    }

    /**
     * @return list<array{type: string, props: array<string, string>, style?: array<string, string>}>
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * Normalized (missing props filled in, style validated, {} when absent) when valid; invalid
     * content is kept as is so that validateBlocks() reports it (API returns 422) instead of
     * failing deserialization. The entity knows no author: the style is checked at the default
     * builder level of PageBlocks::normalize(), the level of an administrator is applied by the caller.
     *
     * @param array<mixed> $blocks
     */
    public function setBlocks(array $blocks): void
    {
        try {
            $this->blocks = PageBlocks::normalize($blocks);
        } catch (InvalidPageBlocks) {
            /** @var list<array{type: string, props: array<string, string>, style?: array<string, string>}> $invalid rejected by validateBlocks() before any flush */
            $invalid = $blocks;
            $this->blocks = $invalid;
        }
        $this->touch();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getGrapesData(): ?array
    {
        return $this->grapesData;
    }

    /**
     * @param array<string, mixed>|null $grapesData
     */
    public function setGrapesData(?array $grapesData): void
    {
        $this->grapesData = [] === $grapesData ? null : $grapesData;
        $this->touch();
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[Assert\Callback]
    public function validateBlocks(ExecutionContextInterface $context): void
    {
        try {
            PageBlocks::normalize($this->blocks);
        } catch (InvalidPageBlocks $exception) {
            $context->buildViolation($exception->getMessage())->atPath('blocks')->addViolation();
        }
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
