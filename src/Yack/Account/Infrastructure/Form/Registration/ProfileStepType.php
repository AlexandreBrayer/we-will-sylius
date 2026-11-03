<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Form\Registration;

use App\Yack\Account\Application\Registration\RegistrationData;
use App\Yack\Account\Domain\CustomerProfile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ProfileStepType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('profile', ChoiceType::class, [
            'label' => 'Vous êtes',
            'expanded' => true,
            'choices' => [
                'Installateur / professionnel du génie climatique (PRO)' => CustomerProfile::PRO_GROUP_CODE,
                'Client e-commerce (ECOM)' => CustomerProfile::ECOM_GROUP_CODE,
            ],
            'constraints' => [new NotBlank(message: 'Choisissez votre profil.')],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => RegistrationData::class]);
    }
}
