<?php

declare(strict_types=1);

namespace App\Entity\Content;

use App\Yack\Content\Domain\CmsSectionName;
use App\Yack\Content\Domain\CmsSectionRefused;
use Doctrine\ORM\Mapping as ORM;

/**
 * Reusable section of the builder (D12, Content\Domain\CmsSections): a name and blocks `{type, props, style}`
 * saved from a page or a template, offered by « Mes sections » in the palette. Inserting it copies its blocks
 * into the page (no live link): renaming or deleting a section changes no page.
 *
 * `blocks` are free blocks only, normalized and checked against the builder level of the author when the
 * section is created (Application\CreateCmsSection), never changed afterwards. The author is copied at that
 * time, without a foreign key to the administrators (as the media library and the history do).
 *
 * API: App\Yack\Content\Infrastructure\Api\CmsSectionView (/api/v2/admin/cms-sections).
 */
#[ORM\Entity]
#[ORM\Table(name: 'yack_cms_section')]
class CmsSection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: CmsSectionName::MAX)]
    private string $name;

    /** @var list<array{type: string, props: array<string, string>, style: array<string, string>}> */
    #[ORM\Column(type: 'json')]
    private array $blocks;

    /** Name of the administrator who saved it, null when saved without one (commands). */
    #[ORM\Column(name: 'author_name', type: 'string', length: 255, nullable: true)]
    private ?string $authorName;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /**
     * @param list<array{type: string, props: array<string, string>, style: array<string, string>}> $blocks normalized (PageBlocks::normalize())
     *
     * @throws CmsSectionRefused empty or too long name
     */
    public function __construct(string $name, array $blocks, ?string $authorName = null, ?\DateTimeImmutable $createdAt = null)
    {
        $this->name = CmsSectionName::clean($name);
        $this->blocks = $blocks;
        $this->authorName = null === $authorName || '' === trim($authorName) ? null : mb_substr(trim($authorName), 0, 255);
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @throws CmsSectionRefused empty or too long name; the section keeps its name
     */
    public function rename(string $name, ?\DateTimeImmutable $at = null): void
    {
        $this->name = CmsSectionName::clean($name);
        $this->updatedAt = $at ?? new \DateTimeImmutable();
    }

    /**
     * @return list<array{type: string, props: array<string, string>, style: array<string, string>}>
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    public function getAuthorName(): ?string
    {
        return $this->authorName;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
