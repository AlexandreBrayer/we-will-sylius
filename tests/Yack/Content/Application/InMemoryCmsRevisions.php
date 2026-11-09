<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Application;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsRevision;
use App\Entity\Content\CmsTemplate;
use App\Yack\Content\Domain\CmsRevisions;
use App\Yack\Content\Domain\CmsRevisionSummary;

/**
 * CmsRevisions as Doctrine runs it: add() and removeOldest() are pending until flush(), which plays the
 * flush of the caller (identifiers given in order, removals applied).
 */
final class InMemoryCmsRevisions implements CmsRevisions
{
    /** @var list<CmsRevision> stored, oldest first */
    public array $stored = [];

    /** @var list<CmsRevision> */
    public array $pending = [];

    public int $removeCalls = 0;

    /** @var list<int> */
    private array $removed = [];

    private int $nextId = 1;

    public function add(CmsRevision $revision): void
    {
        $this->pending[] = $revision;
    }

    public function find(int $id): ?CmsRevision
    {
        foreach ($this->stored as $revision) {
            if ($revision->getId() === $id) {
                return $revision;
            }
        }

        return null;
    }

    public function summaries(CmsPage|CmsTemplate $target): array
    {
        return array_map(static fn (CmsRevision $revision): CmsRevisionSummary => new CmsRevisionSummary(
            (int) $revision->getId(),
            $revision->getCreatedAt(),
            $revision->author(),
            $revision->getStatus(),
            $revision->getTitle(),
            $revision->getBlockCount(),
        ), $this->newestOf($target));
    }

    public function removeOldest(CmsPage|CmsTemplate $target, int $keep): int
    {
        ++$this->removeCalls;
        $ids = array_map(static fn (CmsRevision $revision): int => (int) $revision->getId(), \array_slice($this->newestOf($target), max(0, $keep)));
        array_push($this->removed, ...$ids);

        return \count($ids);
    }

    public function flush(): void
    {
        foreach ($this->pending as $revision) {
            self::identify($revision, $this->nextId++);
            $this->stored[] = $revision;
        }
        $this->pending = [];
        $this->stored = array_values(array_filter($this->stored, fn (CmsRevision $revision): bool => !\in_array($revision->getId(), $this->removed, true)));
        $this->removed = [];
    }

    /** Identifier that Doctrine gives at the insert (entities of src/Entity have no setter for it). */
    public static function identify(object $entity, int $id): void
    {
        (new \ReflectionProperty($entity, 'id'))->setValue($entity, $id);
    }

    /**
     * @return list<CmsRevision>
     */
    private function newestOf(CmsPage|CmsTemplate $target): array
    {
        if (null === $target->getId()) {
            return [];
        }

        return array_reverse(array_values(array_filter(
            $this->stored,
            static fn (CmsRevision $revision): bool => $revision->getPage() === $target || $revision->getTemplate() === $target,
        )));
    }
}
