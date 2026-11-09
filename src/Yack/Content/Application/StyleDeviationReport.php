<?php

declare(strict_types=1);

namespace App\Yack\Content\Application;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsTemplate;
use App\Yack\Content\Domain\BlockType;
use App\Yack\Content\Domain\CmsPages;
use App\Yack\Content\Domain\CmsRevisions;
use App\Yack\Content\Domain\CmsRevisionTarget;
use App\Yack\Content\Domain\CmsTemplates;
use App\Yack\Content\Domain\InvalidStyleReplacement;
use App\Yack\Content\Domain\PageBlocks;
use App\Yack\Content\Domain\StyleReplacement;

/**
 * Report of the charte deviations: every free style value of every page and template (draft or
 * published), computed on reading from the stored blocks (PageBlocks::deviations()), never stored.
 * Author and date of the last change come from the newest revision of the target (CmsRevisions).
 */
final readonly class StyleDeviationReport
{
    public function __construct(
        private CmsPages $pages,
        private CmsTemplates $templates,
        private CmsRevisions $revisions,
    ) {
    }

    /**
     * Pages (last modified first), then templates (by type and name); in each target, block order.
     *
     * @return list<StyleDeviationLine>
     */
    public function lines(): array
    {
        $lines = [];
        foreach ($this->pages->all() as $page) {
            array_push($lines, ...$this->linesOf($page));
        }
        foreach ($this->templates->all() as $template) {
            array_push($lines, ...$this->linesOf($template));
        }

        return $lines;
    }

    /**
     * Lines of one target and one property; null keeps every target or every property.
     *
     * @param list<StyleDeviationLine> $lines
     *
     * @return list<StyleDeviationLine>
     */
    public static function filter(array $lines, ?string $targetKey, ?string $property): array
    {
        return array_values(array_filter(
            $lines,
            static fn (StyleDeviationLine $line): bool => (null === $targetKey || $line->targetKey === $targetKey) &&
                (null === $property || $line->property === $property),
        ));
    }

    /**
     * Targets holding at least one deviation, in report order: key => label (the choices of the target filter).
     *
     * @param list<StyleDeviationLine> $lines
     *
     * @return array<string, string>
     */
    public static function targets(array $lines): array
    {
        $targets = [];
        foreach ($lines as $line) {
            $targets[$line->targetKey] ??= CmsRevisionTarget::Page === $line->target
                ? sprintf('Page : %s', $line->targetLabel)
                : sprintf('Template %s : %s', mb_strtolower($line->templateType ?? ''), $line->targetLabel);
        }

        return $targets;
    }

    /**
     * « 2 écarts sur 1 page », « 3 écarts sur 2 pages et 1 template », « Aucun écart ».
     *
     * @param list<StyleDeviationLine> $lines
     */
    public static function summary(array $lines): string
    {
        if ([] === $lines) {
            return 'Aucun écart';
        }

        $pages = $templates = [];
        foreach ($lines as $line) {
            if (CmsRevisionTarget::Page === $line->target) {
                $pages[$line->targetKey] = true;
            } else {
                $templates[$line->targetKey] = true;
            }
        }

        $where = array_filter([
            [] === $pages ? null : self::plural(count($pages), 'page', 'pages'),
            [] === $templates ? null : self::plural(count($templates), 'template', 'templates'),
        ]);

        return sprintf('%s sur %s', self::plural(count($lines), 'écart', 'écarts'), implode(' et ', $where));
    }

    /**
     * Distinct free values (property and value), with the number of blocks holding each, in report order;
     * `replaceable` is false for a stored value that is not a well formed free value (shown, not replaced).
     *
     * @param list<StyleDeviationLine> $lines
     *
     * @return list<array{property: string, propertyLabel: string, value: string, color: ?string, blocks: int, replaceable: bool}>
     */
    public static function values(array $lines): array
    {
        $values = [];
        foreach ($lines as $line) {
            $key = $line->property . "\n" . strtolower($line->value);
            if (!isset($values[$key])) {
                $values[$key] = [
                    'property' => $line->property,
                    'propertyLabel' => $line->propertyLabel(),
                    'value' => $line->value,
                    'color' => $line->color(),
                    'blocks' => 0,
                    'replaceable' => self::isReplaceable($line->property, $line->value),
                ];
            }
            ++$values[$key]['blocks'];
        }

        return array_values($values);
    }

    /**
     * @return list<StyleDeviationLine>
     */
    private function linesOf(CmsPage|CmsTemplate $target): array
    {
        $blocks = $target->getBlocks();
        $deviations = PageBlocks::deviations($blocks);
        if ([] === $deviations) {
            return [];
        }

        $last = $this->revisions->summaries($target)[0] ?? null;
        $page = $target instanceof CmsPage;
        $ref = $page ? $target->getSlug() : (string) $target->getId();

        $types = self::typesByPosition($blocks);
        $lines = [];
        foreach ($deviations as $deviation) {
            $type = $types[$deviation->block] ?? '';
            $lines[] = new StyleDeviationLine(
                $page ? CmsRevisionTarget::Page : CmsRevisionTarget::Template,
                ($page ? 'page:' : 'template:') . $ref,
                $ref,
                $page ? $target->getTitle() : $target->getName(),
                $page ? null : $target->getType()->label(),
                $deviation->block,
                $type,
                BlockType::tryFrom($type)?->label() ?? $type,
                $deviation->property,
                $deviation->value,
                $last?->author,
                $last?->createdAt,
            );
        }

        return $lines;
    }

    /**
     * Type of each block by position (from 1), stored content read as is like PageBlocks::deviations():
     * blocks stored under keys (a JSON object, only possible in SQL) are counted in order.
     *
     * @param array<mixed> $blocks
     *
     * @return array<int, string>
     */
    private static function typesByPosition(array $blocks): array
    {
        $types = [];
        foreach (array_values($blocks) as $index => $block) {
            if (is_array($block) && is_string($block['type'] ?? null)) {
                $types[$index + 1] = $block['type'];
            }
        }

        return $types;
    }

    private static function isReplaceable(string $property, string $value): bool
    {
        try {
            StyleReplacement::freeValue($property, $value);

            return true;
        } catch (InvalidStyleReplacement) {
            return false;
        }
    }

    private static function plural(int $count, string $one, string $many): string
    {
        return sprintf('%d %s', $count, $count > 1 ? $many : $one);
    }
}
