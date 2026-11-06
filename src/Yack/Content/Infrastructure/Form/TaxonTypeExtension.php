<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Form;

use App\Entity\Taxonomy\Taxon;
use App\Yack\Content\Domain\CmsTemplateType;
use Sylius\Bundle\AdminBundle\Form\Type\TaxonType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * BO taxon form: « Template CMS » of the category page (Taxon::$cmsTemplate) and of the product
 * pages of the category (Taxon::$productCmsTemplate), section added by sylius_twig_hooks (config/packages/yack.yaml).
 */
final class TaxonTypeExtension extends AbstractTypeExtension
{
    public function __construct(private readonly CmsTemplateChoices $choices)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $taxon = $event->getData();
            $taxon = $taxon instanceof Taxon ? $taxon : null;
            $form = $event->getForm();

            $this->choices->add($form, 'cmsTemplate', CmsTemplateType::Taxon, $taxon?->getCmsTemplate(), 'Template CMS de la page catégorie');
            $this->choices->add($form, 'productCmsTemplate', CmsTemplateType::Product, $taxon?->getProductCmsTemplate(), 'Template CMS des fiches produit');
        });
    }

    public static function getExtendedTypes(): iterable
    {
        return [TaxonType::class];
    }
}
