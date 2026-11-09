<?php

declare(strict_types=1);

namespace App\Entity\Content;

use App\Yack\Content\Domain\CmsPageStatus;
use App\Yack\Content\Domain\CmsRevisionAuthor;
use App\Yack\Content\Domain\CmsRevisionTarget;
use App\Yack\Content\Domain\PageBlocks;
use Doctrine\ORM\Mapping as ORM;

/**
 * Saved version of a CMS page or template (history of the builder, Content\Domain\CmsRevisions): one per
 * successful save, the 50 newest kept per target (Application\RecordCmsRevision). Exactly one of `page`
 * and `template` is set; deleting the target deletes its revisions (ON DELETE CASCADE).
 *
 * `content` holds what the builder needs to load the version again, under the names of the PUT of the
 * target: page `title`, `metaTitle`, `metaDescription`, `status`, `blocks` (with `style`), `grapesData`;
 * template `name`, `type`, `isDefault`, `status`, `blocks`, `grapesData`. `title`, `status` and
 * `blockCount` repeat it for the list, read without the content (`blockCount`: every block, containers and
 * their blocks included, PageBlocks::count()). Never changed once written.
 *
 * API: App\Yack\Content\Infrastructure\Api\CmsRevisionView.
 */
#[ORM\Entity]
#[ORM\Table(name: 'yack_cms_revision')]
class CmsRevision
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CmsPage::class)]
    #[ORM\JoinColumn(name: 'page_id', nullable: true, onDelete: 'CASCADE')]
    private ?CmsPage $page = null;

    #[ORM\ManyToOne(targetEntity: CmsTemplate::class)]
    #[ORM\JoinColumn(name: 'template_id', nullable: true, onDelete: 'CASCADE')]
    private ?CmsTemplate $template = null;

    /** Page title or template name at the time of the save. */
    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(type: 'string', length: 16, enumType: CmsPageStatus::class)]
    private CmsPageStatus $status;

    #[ORM\Column(name: 'block_count', type: 'integer')]
    private int $blockCount;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $content;

    #[ORM\Column(name: 'author_first_name', type: 'string', length: 255, nullable: true)]
    private ?string $authorFirstName = null;

    #[ORM\Column(name: 'author_last_name', type: 'string', length: 255, nullable: true)]
    private ?string $authorLastName = null;

    /** Name shown in the history, null when saved without an administrator (fixtures, commands). */
    #[ORM\Column(name: 'author_name', type: 'string', length: 255, nullable: true)]
    private ?string $authorName = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed> $content
     */
    private function __construct(string $title, CmsPageStatus $status, int $blockCount, array $content, ?CmsRevisionAuthor $author, ?\DateTimeImmutable $createdAt)
    {
        $this->title = mb_substr($title, 0, 255);
        $this->status = $status;
        $this->blockCount = $blockCount;
        $this->content = $content;
        $this->authorFirstName = $author?->firstName;
        $this->authorLastName = $author?->lastName;
        $this->authorName = $author?->name;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    /** Current content of the page, as it is about to be written. */
    public static function ofPage(CmsPage $page, ?CmsRevisionAuthor $author, ?\DateTimeImmutable $createdAt = null): self
    {
        $revision = new self($page->getTitle(), $page->getStatus(), PageBlocks::count($page->getBlocks()), [
            'title' => $page->getTitle(),
            'metaTitle' => $page->getMetaTitle(),
            'metaDescription' => $page->getMetaDescription(),
            'status' => $page->getStatus()->value,
            'blocks' => $page->getBlocks(),
            'grapesData' => $page->getGrapesData(),
        ], $author, $createdAt);
        $revision->page = $page;

        return $revision;
    }

    /** Current content of the template, as it is about to be written. */
    public static function ofTemplate(CmsTemplate $template, ?CmsRevisionAuthor $author, ?\DateTimeImmutable $createdAt = null): self
    {
        $revision = new self($template->getName(), $template->getStatus(), PageBlocks::count($template->getBlocks()), [
            'name' => $template->getName(),
            'type' => $template->getType()->value,
            'isDefault' => $template->isDefault(),
            'status' => $template->getStatus()->value,
            'blocks' => $template->getBlocks(),
            'grapesData' => $template->getGrapesData(),
        ], $author, $createdAt);
        $revision->template = $template;

        return $revision;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function target(): CmsRevisionTarget
    {
        return null !== $this->page ? CmsRevisionTarget::Page : CmsRevisionTarget::Template;
    }

    public function getPage(): ?CmsPage
    {
        return $this->page;
    }

    public function getTemplate(): ?CmsTemplate
    {
        return $this->template;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getStatus(): CmsPageStatus
    {
        return $this->status;
    }

    public function getBlockCount(): int
    {
        return $this->blockCount;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContent(): array
    {
        return $this->content;
    }

    public function author(): ?CmsRevisionAuthor
    {
        return CmsRevisionAuthor::stored($this->authorFirstName, $this->authorLastName, $this->authorName);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
