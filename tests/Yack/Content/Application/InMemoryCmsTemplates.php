<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Application;

use App\Entity\Content\CmsTemplate;
use App\Yack\Content\Domain\CmsTemplates;
use App\Yack\Content\Domain\CmsTemplateType;

/** CmsTemplates over a list given in the order of all() (by type, then name). */
final class InMemoryCmsTemplates implements CmsTemplates
{
    /**
     * @param list<CmsTemplate> $templates
     */
    public function __construct(private array $templates = [])
    {
    }

    public function find(int $id): ?CmsTemplate
    {
        foreach ($this->templates as $template) {
            if ($template->getId() === $id) {
                return $template;
            }
        }

        return null;
    }

    public function findDefault(CmsTemplateType $type): ?CmsTemplate
    {
        foreach ($this->published($type) as $template) {
            if ($template->isDefault()) {
                return $template;
            }
        }

        return null;
    }

    public function published(CmsTemplateType $type): array
    {
        return array_values(array_filter($this->templates, static fn (CmsTemplate $template): bool => $template->getType() === $type && $template->isPublished()));
    }

    public function all(): array
    {
        return $this->templates;
    }

    public function add(CmsTemplate $template): void
    {
        $this->templates[] = $template;
    }
}
