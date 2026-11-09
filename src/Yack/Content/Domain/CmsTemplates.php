<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

use App\Entity\Content\CmsTemplate;

interface CmsTemplates
{
    public function find(int $id): ?CmsTemplate;

    /** Published default template of the type, null when none. */
    public function findDefault(CmsTemplateType $type): ?CmsTemplate;

    /** @return list<CmsTemplate> published templates of the type, by name (choices of the back-office forms) */
    public function published(CmsTemplateType $type): array;

    /** @return list<CmsTemplate> every template, by type then name */
    public function all(): array;

    public function add(CmsTemplate $template): void;
}
