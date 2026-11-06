<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Admin: products / categories using a CMS template (same data as the back-office « Template CMS » fields).
 *  - GET /api/v2/admin/cms-templates/{id}/assignments
 *  - PUT /api/v2/admin/cms-templates/{id}/assignments {"productCodes": [...], "taxonCodes": [...]} — replaces both lists
 * Product template: productCodes = products, taxonCodes = categories whose products use it.
 * Taxon template: taxonCodes = category pages (productCodes must be empty).
 */
#[ApiResource(
    shortName: 'CmsTemplateAssignments',
    operations: [
        new Get(
            uriTemplate: '/admin/cms-templates/{id}/assignments',
            normalizationContext: ['groups' => ['yack:cms-template:assignments:read']],
            security: "is_granted('ROLE_API_ACCESS')",
            provider: CmsTemplateAssignmentsProvider::class,
            name: 'app_yack_api_admin_cms_template_assignments_get',
        ),
        new Put(
            uriTemplate: '/admin/cms-templates/{id}/assignments',
            normalizationContext: ['groups' => ['yack:cms-template:assignments:read']],
            denormalizationContext: ['groups' => ['yack:cms-template:assignments:write']],
            security: "is_granted('ROLE_API_ACCESS')",
            provider: CmsTemplateAssignmentsProvider::class,
            processor: CmsTemplateAssignmentsProcessor::class,
            name: 'app_yack_api_admin_cms_template_assignments_put',
        ),
    ],
)]
final class CmsTemplateAssignmentsView
{
    /**
     * @param list<string>                             $productCodes
     * @param list<string>                             $taxonCodes
     * @param list<array{code: string, name: string}> $products
     * @param list<array{code: string, name: string}> $taxons
     */
    public function __construct(
        /** Template id. */
        #[ApiProperty(identifier: true)]
        #[Groups(['yack:cms-template:assignments:read'])]
        public int $id,
        /** product | taxon */
        #[Groups(['yack:cms-template:assignments:read'])]
        public string $type,
        #[Groups(['yack:cms-template:assignments:read', 'yack:cms-template:assignments:write'])]
        #[Assert\All([new Assert\Type('string')])]
        public array $productCodes = [],
        #[Groups(['yack:cms-template:assignments:read', 'yack:cms-template:assignments:write'])]
        #[Assert\All([new Assert\Type('string')])]
        public array $taxonCodes = [],
        /** Assigned products (code, French name), by code. */
        #[Groups(['yack:cms-template:assignments:read'])]
        public array $products = [],
        /** Assigned categories (code, French name), in tree order. */
        #[Groups(['yack:cms-template:assignments:read'])]
        public array $taxons = [],
    ) {
    }
}
