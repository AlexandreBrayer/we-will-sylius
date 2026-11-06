<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

use App\Entity\Content\CmsTemplate;

final readonly class ResolvedCmsTemplate
{
    public function __construct(
        public CmsTemplate $template,
        public CmsTemplateSource $source,
        /** Product or taxon code holding the assignment, null for the default template. */
        public ?string $sourceCode = null,
        /** Product or taxon name holding the assignment (back-office display), null for the default template. */
        public ?string $sourceName = null,
    ) {
    }
}
