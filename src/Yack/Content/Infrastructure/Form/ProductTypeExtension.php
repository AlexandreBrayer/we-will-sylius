<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Form;

use App\Entity\Product\Product;
use App\Yack\Content\Domain\CmsTemplateType;
use Sylius\Bundle\AdminBundle\Form\Type\ProductType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/** BO product form: « Template CMS » (Product::$cmsTemplate), section added by sylius_twig_hooks (config/packages/yack.yaml). */
final class ProductTypeExtension extends AbstractTypeExtension
{
    public function __construct(private readonly CmsTemplateChoices $choices)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $product = $event->getData();
            $this->choices->add(
                $event->getForm(),
                'cmsTemplate',
                CmsTemplateType::Product,
                $product instanceof Product ? $product->getCmsTemplate() : null,
                'Template CMS',
            );
        });
    }

    public static function getExtendedTypes(): iterable
    {
        return [ProductType::class];
    }
}
