<?php

declare(strict_types=1);

namespace App\Yack\Content\Application;

use App\Entity\Content\CmsTemplate;
use App\Entity\Product\Product;
use App\Entity\Taxonomy\Taxon;
use App\Yack\Content\Domain\CmsTemplates;
use App\Yack\Content\Domain\CmsTemplateSource;
use App\Yack\Content\Domain\CmsTemplateType;
use App\Yack\Content\Domain\ResolvedCmsTemplate;

/**
 * Effective (published) template of a common page. Unpublished assigned templates are skipped,
 * as if nothing were assigned at that level.
 *  - product page: template of the product, else product template of its main taxon then of
 *    its ancestors (Taxon::$productCmsTemplate), else default product template;
 *  - category page: template of the taxon, else of its ancestors (Taxon::$cmsTemplate), else default taxon template.
 * The "inherited" variants ignore the page's own assignment: what applies when the back-office
 * field is left empty.
 */
final readonly class CmsTemplateResolver
{
    public function __construct(private CmsTemplates $templates)
    {
    }

    public function forProduct(Product $product): ?ResolvedCmsTemplate
    {
        $own = $product->getCmsTemplate();
        if (null !== $own && $own->isPublished()) {
            return new ResolvedCmsTemplate($own, CmsTemplateSource::Product, (string) $product->getCode(), (string) $product->getName());
        }

        return $this->inheritedForProduct($product);
    }

    public function inheritedForProduct(Product $product): ?ResolvedCmsTemplate
    {
        $mainTaxon = $product->getMainTaxon();

        return $this->fromTaxons($mainTaxon instanceof Taxon ? $mainTaxon : null, CmsTemplateType::Product);
    }

    public function forTaxon(Taxon $taxon): ?ResolvedCmsTemplate
    {
        return $this->fromTaxons($taxon, CmsTemplateType::Taxon);
    }

    public function inheritedForTaxon(Taxon $taxon): ?ResolvedCmsTemplate
    {
        return $this->fromTaxons(self::parentOf($taxon), CmsTemplateType::Taxon);
    }

    /** Product template the products of this category get when its "products" field is left empty. */
    public function inheritedProductTemplateForTaxon(Taxon $taxon): ?ResolvedCmsTemplate
    {
        return $this->fromTaxons(self::parentOf($taxon), CmsTemplateType::Product);
    }

    private function fromTaxons(?Taxon $taxon, CmsTemplateType $type): ?ResolvedCmsTemplate
    {
        for (; null !== $taxon; $taxon = self::parentOf($taxon)) {
            $template = self::assignedTo($taxon, $type);
            if (null !== $template && $template->isPublished()) {
                return new ResolvedCmsTemplate($template, CmsTemplateSource::Taxon, (string) $taxon->getCode(), (string) $taxon->getName());
            }
        }

        $default = $this->templates->findDefault($type);

        return null === $default ? null : new ResolvedCmsTemplate($default, CmsTemplateSource::Default);
    }

    private static function assignedTo(Taxon $taxon, CmsTemplateType $type): ?CmsTemplate
    {
        return CmsTemplateType::Product === $type ? $taxon->getProductCmsTemplate() : $taxon->getCmsTemplate();
    }

    private static function parentOf(Taxon $taxon): ?Taxon
    {
        $parent = $taxon->getParent();

        return $parent instanceof Taxon ? $parent : null;
    }
}
