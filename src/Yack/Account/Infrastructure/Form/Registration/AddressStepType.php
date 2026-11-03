<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Form\Registration;

use App\Yack\Account\Application\Registration\RegistrationData;
use Sylius\Bundle\AddressingBundle\Form\Type\CountryCodeChoiceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Billing address (becomes the default address) + delivery address: same, or another one.
 */
final class AddressStepType extends AbstractType
{
    private const REQUIRED_MESSAGES = [
        'Street' => 'Indiquez l\'adresse (numéro et voie).',
        'Postcode' => 'Indiquez le code postal.',
        'City' => 'Indiquez la ville.',
        'CountryCode' => 'Choisissez un pays.',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (['billing', 'shipping'] as $prefix) {
            $billing = 'billing' === $prefix;
            $builder
                ->add($prefix . 'Street', TextType::class, [
                    'label' => 'Adresse',
                    'required' => $billing,
                    'constraints' => $billing ? [new NotBlank(message: self::REQUIRED_MESSAGES['Street']), new Length(max: 255)] : [new Length(max: 255)],
                ])
                ->add($prefix . 'Postcode', TextType::class, [
                    'label' => 'Code postal',
                    'required' => $billing,
                    'constraints' => $billing ? [new NotBlank(message: self::REQUIRED_MESSAGES['Postcode']), new Length(max: 10)] : [new Length(max: 10)],
                ])
                ->add($prefix . 'City', TextType::class, [
                    'label' => 'Ville',
                    'required' => $billing,
                    'constraints' => $billing ? [new NotBlank(message: self::REQUIRED_MESSAGES['City']), new Length(max: 255)] : [new Length(max: 255)],
                ])
                ->add($prefix . 'CountryCode', CountryCodeChoiceType::class, [
                    'label' => 'Pays',
                    'required' => $billing,
                    'placeholder' => 'Sélectionner',
                    'constraints' => $billing ? [new NotBlank(message: self::REQUIRED_MESSAGES['CountryCode'])] : [],
                ]);

            if ($billing) {
                $builder->add('shippingSameAsBilling', CheckboxType::class, [
                    'label' => 'Livrer à cette adresse',
                    'required' => false,
                ]);
            }
        }

        // Delivery address required only when it differs from the billing one.
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();
            if (!$data instanceof RegistrationData || $data->shippingSameAsBilling) {
                return;
            }

            $form = $event->getForm();
            foreach (self::REQUIRED_MESSAGES as $field => $message) {
                $value = $form->get('shipping' . $field)->getData();
                if (null === $value || '' === $value) {
                    $form->get('shipping' . $field)->addError(new FormError($message));
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => RegistrationData::class]);
    }
}
