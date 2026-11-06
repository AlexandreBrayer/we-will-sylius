<?php

declare(strict_types=1);

namespace App\Entity\Product;

use App\Entity\Content\CmsTemplate;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Product as BaseProduct;
use Sylius\Component\Product\Model\ProductTranslationInterface;
use Sylius\MolliePlugin\Entity\ProductInterface;
use Sylius\MolliePlugin\Entity\ProductTrait;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_product')]
class Product extends BaseProduct implements ProductInterface
{
    use ProductTrait;

    /** Product CMS template assigned to this product (null = inherited from its categories, then default). */
    #[ORM\ManyToOne(targetEntity: CmsTemplate::class, inversedBy: 'products')]
    #[ORM\JoinColumn(name: 'cms_template_id', nullable: true, onDelete: 'SET NULL')]
    private ?CmsTemplate $cmsTemplate = null;

    public function getCmsTemplate(): ?CmsTemplate
    {
        return $this->cmsTemplate;
    }

    public function setCmsTemplate(?CmsTemplate $cmsTemplate): void
    {
        $this->cmsTemplate = $cmsTemplate;
    }

    protected function createTranslation(): ProductTranslationInterface
    {
        return new ProductTranslation();
    }
}
