<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Form\Registration;

use App\Yack\Account\Application\Registration\RegistrationData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

final class ContactStepType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'constraints' => [new NotBlank(message: 'Indiquez votre nom.'), new Length(max: 255)],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [new NotBlank(message: 'Indiquez votre prénom.'), new Length(max: 255)],
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-mail',
                'help' => 'Il servira d\'identifiant ; un lien d\'activation y sera envoyé.',
                'constraints' => [
                    new NotBlank(message: 'Indiquez votre adresse e-mail.'),
                    new Email(message: 'Cette adresse e-mail n\'est pas valide.'),
                ],
            ])
            ->add('phoneNumber', TelType::class, [
                'label' => 'Téléphone',
                'attr' => ['placeholder' => '04 42 00 00 00'],
                'constraints' => [
                    new NotBlank(message: 'Indiquez un numéro de téléphone.'),
                    new Regex(pattern: '/^\+?[0-9 .()-]{10,20}$/', message: 'Numéro de téléphone invalide (10 chiffres attendus).'),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
                'first_options' => ['label' => 'Mot de passe', 'help' => '8 caractères minimum.'],
                'second_options' => ['label' => 'Confirmation du mot de passe'],
                'constraints' => [
                    new NotBlank(message: 'Choisissez un mot de passe.'),
                    new Length(min: 8, max: 4096, minMessage: 'Le mot de passe doit comporter au moins {{ limit }} caractères.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => RegistrationData::class]);
    }
}
