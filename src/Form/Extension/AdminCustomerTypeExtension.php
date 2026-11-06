<?php

declare(strict_types=1);

namespace App\Form\Extension;

use Sylius\Bundle\AdminBundle\Form\Type\CustomerType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;

final class AdminCustomerTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('escompteRate', PercentType::class, [
            'label' => 'app.form.customer.escompte_rate',
            'required' => false,
            'type' => 'integer',
            'scale' => 2,
            'constraints' => [
                new GreaterThanOrEqual(0),
                new LessThanOrEqual(100),
            ],
        ]);

        $builder->add('escompteDays', IntegerType::class, [
            'label' => 'app.form.customer.escompte_days',
            'required' => false,
            'attr' => ['min' => 0],
            'constraints' => [
                new GreaterThanOrEqual(0),
            ],
        ]);
    }

    public static function getExtendedTypes(): iterable
    {
        return [CustomerType::class];
    }
}
