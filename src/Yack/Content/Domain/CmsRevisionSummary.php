<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/** Line of the history of a page or a template: what the list shows, without the content. */
final readonly class CmsRevisionSummary
{
    public function __construct(
        public int $id,
        public \DateTimeImmutable $createdAt,
        public ?CmsRevisionAuthor $author,
        public CmsPageStatus $status,
        public string $title,
        public int $blockCount,
    ) {
    }
}
