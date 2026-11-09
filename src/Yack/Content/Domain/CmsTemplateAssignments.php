<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

use App\Entity\Content\CmsTemplate;

/**
 * Products and categories a template is assigned to (same data as the « Template CMS » fields
 * of the back-office product / taxon forms):
 *  - product template: products (Product::$cmsTemplate) and categories whose products use it (Taxon::$productCmsTemplate);
 *  - taxon template: categories (Taxon::$cmsTemplate).
 */
interface CmsTemplateAssignments
{
    /**
     * @return array{products: list<array{code: string, name: string}>, taxons: list<array{code: string, name: string}>}
     */
    public function of(CmsTemplate $template): array;

    /**
     * Replaces the assignments: codes not listed lose the template, listed ones get it (taking it
     * from another template if needed).
     *
     * @param list<string> $productCodes
     * @param list<string> $taxonCodes
     *
     * @throws InvalidCmsTemplateAssignment
     */
    public function replace(CmsTemplate $template, array $productCodes, array $taxonCodes): void;
}
