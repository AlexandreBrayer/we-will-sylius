<?php

declare(strict_types=1);

namespace App\Yack\Content\Application;

use App\Entity\Content\CmsSection;
use App\Yack\Content\Domain\BlockType;
use App\Yack\Content\Domain\BuilderLevel;
use App\Yack\Content\Domain\CharteGuard;
use App\Yack\Content\Domain\CmsMediaLibrary;
use App\Yack\Content\Domain\CmsSectionName;
use App\Yack\Content\Domain\CmsSectionRefused;
use App\Yack\Content\Domain\CmsSections;
use App\Yack\Content\Domain\InvalidPageBlocks;
use App\Yack\Content\Domain\PageBlocks;
use App\Yack\Content\Domain\StyleNotAllowed;

/**
 * « Enregistrer comme section » (D12): blocks validated exactly as the blocks of a page (PageBlocks: types,
 * props, links, images of the media library, style within its bounds), then the builder level of the author
 * (CharteGuard, B0-3 rule). A section is new content: every free value it holds is new, so a `charte`
 * account cannot save one that holds a free value.
 *
 * Free blocks only: a section is offered in every editor (pages, product and category templates), and a
 * block bound to a template type would be refused by a page or by the other type (and the mandatory block of
 * a template exists once).
 */
final readonly class CreateCmsSection
{
    public function __construct(
        private CmsSections $sections,
        private CmsMediaLibrary $media,
    ) {
    }

    /**
     * @throws CmsSectionRefused name, or blocks (malformed, bound, empty, free value refused by $level)
     */
    public function __invoke(string $name, mixed $blocks, BuilderLevel $level, ?string $authorName): CmsSection
    {
        $name = CmsSectionName::clean($name);
        self::assertFreeBlocks($blocks);

        try {
            $normalized = PageBlocks::normalize($blocks, null, BuilderLevel::Libre, $this->media);
            CharteGuard::assertAllowed($level, $normalized);
        } catch (InvalidPageBlocks|StyleNotAllowed $refused) {
            throw CmsSectionRefused::blocks($refused->getMessage());
        }
        if ([] === $normalized) {
            throw CmsSectionRefused::blocks('Une section contient au moins un bloc.');
        }

        $section = new CmsSection($name, $normalized, $authorName);
        $this->sections->add($section);

        return $section;
    }

    /**
     * @throws CmsSectionRefused the first block bound to a template type
     */
    private static function assertFreeBlocks(mixed $blocks): void
    {
        if (!is_array($blocks)) {
            return;
        }
        foreach (array_values($blocks) as $position => $block) {
            $type = is_array($block) && is_string($block['type'] ?? null) ? BlockType::tryFrom($block['type']) : null;
            if (null !== $type && null !== $type->scope()) {
                throw CmsSectionRefused::blocks(sprintf(
                    'Bloc n°%d (%s) : un bloc lié à la fiche %s ne s\'enregistre pas comme section : seuls les blocs Yack libres le peuvent.',
                    $position + 1,
                    $type->label(),
                    mb_strtolower($type->scope()->label()),
                ));
            }
        }
    }
}
