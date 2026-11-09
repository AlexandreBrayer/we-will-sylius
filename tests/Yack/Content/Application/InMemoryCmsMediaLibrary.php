<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Application;

use App\Entity\Content\CmsMedia;
use App\Yack\Content\Domain\CmsMediaLibrary;

/** Hand-written fake of the media library port: ids given on add(), newest first, counts the writes. */
final class InMemoryCmsMediaLibrary implements CmsMediaLibrary
{
    /** @var array<int, CmsMedia> */
    public array $media = [];

    public int $saves = 0;

    public ?\Throwable $failOnAdd = null;

    private int $nextId = 1;

    public function exists(string $path): bool
    {
        foreach ($this->media as $media) {
            if ($media->getPath() === $path) {
                return true;
            }
        }

        return false;
    }

    public function search(?string $query = null): array
    {
        $found = array_values(array_filter(
            $this->media,
            static fn (CmsMedia $media): bool => null === $query || '' === trim($query) || str_contains(mb_strtolower($media->getOriginalName()), mb_strtolower(trim($query))),
        ));
        usort($found, static fn (CmsMedia $a, CmsMedia $b): int => [$b->getCreatedAt(), $b->getId()] <=> [$a->getCreatedAt(), $a->getId()]);

        return $found;
    }

    public function find(int $id): ?CmsMedia
    {
        return $this->media[$id] ?? null;
    }

    public function add(CmsMedia $media): void
    {
        if (null !== $this->failOnAdd) {
            throw $this->failOnAdd;
        }
        $id = $this->nextId++;
        (new \ReflectionProperty(CmsMedia::class, 'id'))->setValue($media, $id);
        $this->media[$id] = $media;
    }

    public function save(CmsMedia $media): void
    {
        ++$this->saves;
    }

    public function remove(CmsMedia $media): void
    {
        unset($this->media[(int) $media->getId()]);
    }
}
