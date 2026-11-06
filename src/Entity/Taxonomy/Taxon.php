<?php

declare(strict_types=1);

namespace App\Entity\Taxonomy;

use App\Entity\Content\CmsTemplate;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Taxon as BaseTaxon;
use Sylius\Component\Taxonomy\Model\TaxonTranslationInterface;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_taxon')]
class Taxon extends BaseTaxon
{
    /** Taxon CMS template of this category page (null = inherited from the ancestors, then default). */
    #[ORM\ManyToOne(targetEntity: CmsTemplate::class, inversedBy: 'taxons')]
    #[ORM\JoinColumn(name: 'cms_template_id', nullable: true, onDelete: 'SET NULL')]
    private ?CmsTemplate $cmsTemplate = null;

    /** Product CMS template of the products of this category and its descendants (null = inherited). */
    #[ORM\ManyToOne(targetEntity: CmsTemplate::class, inversedBy: 'productTaxons')]
    #[ORM\JoinColumn(name: 'product_cms_template_id', nullable: true, onDelete: 'SET NULL')]
    private ?CmsTemplate $productCmsTemplate = null;

    public function getCmsTemplate(): ?CmsTemplate
    {
        return $this->cmsTemplate;
    }

    public function setCmsTemplate(?CmsTemplate $cmsTemplate): void
    {
        $this->cmsTemplate = $cmsTemplate;
    }

    public function getProductCmsTemplate(): ?CmsTemplate
    {
        return $this->productCmsTemplate;
    }

    public function setProductCmsTemplate(?CmsTemplate $productCmsTemplate): void
    {
        $this->productCmsTemplate = $productCmsTemplate;
    }

    protected function createTranslation(): TaxonTranslationInterface
    {
        return new TaxonTranslation();
    }
}
