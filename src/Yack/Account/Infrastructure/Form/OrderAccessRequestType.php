<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Form;

use App\Yack\Account\Application\OrderAccessRequestData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class OrderAccessRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('company', TextType::class, ['label' => 'Société'])
            ->add('siret', TextType::class, ['label' => 'SIRET', 'attr' => ['inputmode' => 'numeric', 'maxlength' => 17]])
            ->add('fullName', TextType::class, ['label' => 'Nom et prénom'])
            ->add('email', EmailType::class, ['label' => 'E-mail'])
            ->add('phone', TelType::class, ['label' => 'Téléphone'])
            ->add('address', TextareaType::class, ['label' => 'Adresse de l\'entreprise', 'attr' => ['rows' => 3]])
            ->add('message', TextareaType::class, [
                'label' => 'Message (facultatif)',
                'required' => false,
                'attr' => ['rows' => 4, 'placeholder' => 'Volumes estimés, agence habituelle, besoins particuliers…'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OrderAccessRequestData::class,
            'csrf_token_id' => 'yack_order_access_request',
        ]);
    }
}
