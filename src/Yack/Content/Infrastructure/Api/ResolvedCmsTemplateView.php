<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;

/**
 * GET /api/v2/shop/cms-templates/resolve?type=product|taxon&code={product or taxon code} (public)
 * — effective published template of a product page / category page (CmsTemplateResolver).
 * 404 when the product / category is unknown or no template applies (no published default).
 */
#[ApiResource(
    shortName: 'ResolvedCmsTemplate',
    operations: [
        new Get(
            uriTemplate: '/shop/cms-templates/resolve',
            provider: ResolvedCmsTemplateProvider::class,
            parameters: [
                'type' => new QueryParameter(schema: ['type' => 'string'], description: 'product (fiche produit) ou taxon (page catégorie)'),
                'code' => new QueryParameter(schema: ['type' => 'string'], description: 'Code du produit ou de la catégorie'),
            ],
            name: 'app_yack_api_shop_cms_template_resolve',
        ),
    ],
)]
final readonly class ResolvedCmsTemplateView
{
    /**
     * @param list<array{type: string, props: array<string, string>}> $blocks
     */
    public function __construct(
        /** Template id. */
        #[ApiProperty(identifier: true)]
        public int $id,
        /** product | taxon */
        public string $type,
        public string $name,
        /** product | taxon | default: where the template is assigned. */
        public string $source,
        /** Product or taxon code holding the assignment (the page's own code or an ancestor category), null for default. */
        public ?string $sourceCode,
        public array $blocks,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
