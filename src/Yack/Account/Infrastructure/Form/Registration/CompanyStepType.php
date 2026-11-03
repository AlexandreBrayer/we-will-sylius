<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Form\Registration;

use App\Yack\Account\Application\Registration\RegistrationData;
use App\Yack\Account\Domain\Siret;
use App\Yack\Account\Infrastructure\Validator\ValidSiret;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Raison sociale and SIRET are required for PRO accounts, optional for ECOM ones
 * (SIRET checked whenever it is filled in). VAT number always optional.
 */
final class CompanyStepType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $required = (bool) $options['company_required'];

        $builder
            ->add('company', TextType::class, [
                'label' => 'Raison sociale',
                'required' => $required,
                'constraints' => [
                    ...($required ? [new NotBlank(message: 'Indiquez la raison sociale de votre entreprise.')] : []),
                    new Length(max: 255, maxMessage: 'La raison sociale ne doit pas dépasser {{ limit }} caractères.'),
                ],
            ])
            ->add('siret', TextType::class, [
                'label' => 'SIRET',
                'required' => $required,
                'help' => '14 chiffres, sans espaces.',
                'attr' => ['inputmode' => 'numeric', 'maxlength' => 17, 'placeholder' => '123 456 789 00012'],
                'constraints' => [
                    ...($required ? [new NotBlank(message: 'Le SIRET est obligatoire pour un compte professionnel.')] : []),
                    new ValidSiret(),
                ],
            ])
            ->add('vatNumber', TextType::class, [
                'label' => 'N° de TVA intracommunautaire (facultatif)',
                'required' => false,
                'attr' => ['placeholder' => 'FR00123456789'],
                'constraints' => [
                    new Callback(static function (?string $value, ExecutionContextInterface $context): void {
                        if (null === $value || '' === $value) {
                            return;
                        }
                        $root = $context->getRoot();
                        $siret = $root instanceof FormInterface ? $root->get('siret')->getData() : null;
                        if (!Siret::isValidFrenchVatNumber($value, is_string($siret) ? $siret : null)) {
                            $context->addViolation('N° de TVA invalide : format FR + clé (2 caractères) + SIREN (9 premiers chiffres du SIRET).');
                        }
                    }),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RegistrationData::class,
            'company_required' => true,
        ]);
        $resolver->setAllowedTypes('company_required', 'bool');
    }
}
