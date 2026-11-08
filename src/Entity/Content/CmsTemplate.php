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
use App\Entity\Product\Product;
use App\Entity\Taxonomy\Taxon;
use App\Yack\Content\Domain\CmsPageStatus;
use App\Yack\Content\Domain\CmsTemplateType;
use App\Yack\Content\Domain\InvalidPageBlocks;
use App\Yack\Content\Domain\PageBlocks;
use App\Yack\Content\Infrastructure\Api\CmsTemplateDuplicateProcessor;
use App\Yack\Content\Infrastructure\Api\CmsTemplateProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Layout of a common page (product page or category page) built with the SvelteKit builder:
 * same content model as CmsPage (ordered Yack blocks + raw GrapesJS data, draft / published),
 * plus free and bound blocks (BlockType::scope()).
 *
 * Assignment (nullable relations, owned by the Sylius resources):
 *  - Product::$cmsTemplate (product template of the product);
 *  - Taxon::$productCmsTemplate (product template of the products of the category and its descendants);
 *  - Taxon::$cmsTemplate (taxon template of the category page and its descendants).
 * Resolution: App\Yack\Content\Application\CmsTemplateResolver. One published default per type.
 *
 * API (prefix /api/v2, admin = JWT of a Sylius administrator): CRUD /admin/cms-templates[/{id}],
 * POST /admin/cms-templates/{id}/duplicate, /admin/cms-templates/{id}/assignments
 * (CmsTemplateAssignmentsView), shop GET /shop/cms-templates/resolve (ResolvedCmsTemplateView).
 */
#[ORM\Entity]
#[ORM\Table(name: 'yack_cms_template')]
#[ORM\Index(name: 'idx_yack_cms_template_type_status', columns: ['type', 'status'])]
#[ApiResource(
    shortName: 'CmsTemplate',
    operations: [
        new GetCollection(
            uriTemplate: '/admin/cms-templates',
            order: ['type' => 'ASC', 'name' => 'ASC'],
            normalizationContext: ['groups' => ['yack:cms-template:admin:index']],
            security: "is_granted('ROLE_API_ACCESS')",
            name: 'app_yack_api_admin_cms_template_get_collection',
        ),
        new Get(
            uriTemplate: '/admin/cms-templates/{id}',
            normalizationContext: ['groups' => ['yack:cms-template:admin:index', 'yack:cms-template:admin:show']],
            security: "is_granted('ROLE_API_ACCESS')",
            name: 'app_yack_api_admin_cms_template_get',
        ),
        new Post(
            uriTemplate: '/admin/cms-templates',
            normalizationContext: ['groups' => ['yack:cms-template:admin:index', 'yack:cms-template:admin:show']],
            denormalizationContext: ['groups' => ['yack:cms-template:admin:create', 'yack:cms-template:admin:update']],
            security: "is_granted('ROLE_API_ACCESS')",
            processor: CmsTemplateProcessor::class,
            name: 'app_yack_api_admin_cms_template_post',
        ),
        new Put(
            uriTemplate: '/admin/cms-templates/{id}',
            normalizationContext: ['groups' => ['yack:cms-template:admin:index', 'yack:cms-template:admin:show']],
            denormalizationContext: ['groups' => ['yack:cms-template:admin:update']],
            security: "is_granted('ROLE_API_ACCESS')",
            processor: CmsTemplateProcessor::class,
            name: 'app_yack_api_admin_cms_template_put',
        ),
        new Delete(
            uriTemplate: '/admin/cms-templates/{id}',
            security: "is_granted('ROLE_API_ACCESS')",
            processor: CmsTemplateProcessor::class,
            name: 'app_yack_api_admin_cms_template_delete',
        ),
        new Post(
            uriTemplate: '/admin/cms-templates/{id}/duplicate',
            status: 201,
            normalizationContext: ['groups' => ['yack:cms-template:admin:index', 'yack:cms-template:admin:show']],
            security: "is_granted('ROLE_API_ACCESS')",
            input: false,
            read: true,
            deserialize: false,
            validate: false,
            processor: CmsTemplateDuplicateProcessor::class,
            name: 'app_yack_api_admin_cms_template_duplicate',
        ),
    ],
    paginationEnabled: false,
)]
class CmsTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[ApiProperty(identifier: true)]
    #[Groups(['yack:cms-template:admin:index'])]
    private ?int $id = null;

    /** Fixed at creation (default: product): assignments depend on it. */
    #[ORM\Column(type: 'string', length: 16, enumType: CmsTemplateType::class)]
    #[Groups(['yack:cms-template:admin:index', 'yack:cms-template:admin:create'])]
    private CmsTemplateType $type;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['yack:cms-template:admin:index', 'yack:cms-template:admin:update'])]
    #[Assert\NotBlank(message: 'Le nom du template est obligatoire.')]
    #[Assert\Length(max: 255)]
    private string $name;

    /** Template used when nothing is assigned up the chain (product / category). One per type, always published. */
    #[ORM\Column(name: 'is_default', type: 'boolean')]
    #[Groups(['yack:cms-template:admin:index', 'yack:cms-template:admin:update'])]
    #[SerializedName('isDefault')]
    private bool $default = false;

    #[ORM\Column(type: 'string', length: 16, enumType: CmsPageStatus::class)]
    #[Groups(['yack:cms-template:admin:index', 'yack:cms-template:admin:update'])]
    private CmsPageStatus $status = CmsPageStatus::Draft;

    /** @var list<array{type: string, props: array<string, string>, style?: array<string, string>}> */
    #[ORM\Column(type: 'json')]
    #[Groups(['yack:cms-template:admin:show', 'yack:cms-template:admin:update'])]
    private array $blocks = [];

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'grapes_data', type: 'json', nullable: true)]
    #[Groups(['yack:cms-template:admin:show', 'yack:cms-template:admin:update'])]
    private ?array $grapesData = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    #[Groups(['yack:cms-template:admin:index'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    #[Groups(['yack:cms-template:admin:index'])]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Product> products assigned directly (product template) */
    #[ORM\OneToMany(targetEntity: Product::class, mappedBy: 'cmsTemplate', fetch: 'EXTRA_LAZY')]
    private Collection $products;

    /** @var Collection<int, Taxon> categories whose products use this template (product template) */
    #[ORM\OneToMany(targetEntity: Taxon::class, mappedBy: 'productCmsTemplate', fetch: 'EXTRA_LAZY')]
    private Collection $productTaxons;

    /** @var Collection<int, Taxon> categories whose page uses this template (taxon template) */
    #[ORM\OneToMany(targetEntity: Taxon::class, mappedBy: 'cmsTemplate', fetch: 'EXTRA_LAZY')]
    private Collection $taxons;

    public function __construct(CmsTemplateType $type = CmsTemplateType::Product, string $name = '')
    {
        $this->type = $type;
        $this->name = $name;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->products = new ArrayCollection();
        $this->productTaxons = new ArrayCollection();
        $this->taxons = new ArrayCollection();
    }

    /** Draft copy, never default, without assignment. */
    public function duplicate(string $name): self
    {
        $copy = new self($this->type, $name);
        $copy->blocks = $this->blocks;
        $copy->grapesData = $this->grapesData;

        return $copy;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): CmsTemplateType
    {
        return $this->type;
    }

    /** Blocks are normalized against the type: re-run when the type arrives after them (deserialization). */
    public function setType(CmsTemplateType $type): void
    {
        $this->type = $type;
        $this->setBlocks($this->blocks);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
        $this->touch();
    }

    public function isDefault(): bool
    {
        return $this->default;
    }

    public function setDefault(bool $default): void
    {
        $this->default = $default;
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
            $this->blocks = PageBlocks::normalize($blocks, $this->type);
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Number of direct assignments (product template: products + categories of products; taxon template: categories).
     *
     * @return array{products: int, taxons: int}
     */
    #[Groups(['yack:cms-template:admin:index'])]
    public function getAssignments(): array
    {
        return CmsTemplateType::Product === $this->type
            ? ['products' => $this->products->count(), 'taxons' => $this->productTaxons->count()]
            : ['products' => 0, 'taxons' => $this->taxons->count()];
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        try {
            PageBlocks::normalize($this->blocks, $this->type);
        } catch (InvalidPageBlocks $exception) {
            $context->buildViolation($exception->getMessage())->atPath('blocks')->addViolation();
        }

        if ($this->default && !$this->isPublished()) {
            $context->buildViolation('Le template par défaut doit être publié.')->atPath('isDefault')->addViolation();
        }
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
