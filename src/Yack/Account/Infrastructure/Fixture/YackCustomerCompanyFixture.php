<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Fixture;

use App\Entity\Customer\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Sets the company identity (raison sociale, SIRET) and the default address of seeded
 * customers; the stock shop_user fixture has no such fields. The default address pre-fills
 * the checkout address step.
 *
 * @phpstan-type AddressOptions array{street: string, postcode: string, city: string, country_code: string, phone_number: ?string}
 * @phpstan-type CustomerOptions array{company: ?string, siret: ?string, vat_number: ?string, address?: AddressOptions}
 */
final class YackCustomerCompanyFixture extends AbstractFixture
{
    /** @param FactoryInterface<AddressInterface> $addressFactory */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'sylius.factory.address')]
        private readonly FactoryInterface $addressFactory,
    ) {
    }

    public function getName(): string
    {
        return 'yack_customer_company';
    }

    /** @param array{customers: array<string, CustomerOptions>} $options */
    public function load(array $options): void
    {
        foreach ($options['customers'] as $email => $identity) {
            $customer = $this->entityManager->getRepository(Customer::class)->findOneBy(['emailCanonical' => mb_strtolower($email)]);
            if (!$customer instanceof Customer) {
                throw new \InvalidArgumentException(sprintf('Customer "%s" not found: load the shop_user fixture first.', $email));
            }

            $customer->setCompany($identity['company']);
            $customer->setSiret($identity['siret']);
            $customer->setVatNumber($identity['vat_number']);

            if (isset($identity['address'])) {
                $customer->setDefaultAddress($this->address($customer, $identity['address']));
            }
        }

        $this->entityManager->flush();
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->arrayNode('customers')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('email')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('company')->defaultNull()->end()
                            ->scalarNode('siret')->defaultNull()->end()
                            ->scalarNode('vat_number')->defaultNull()->end()
                            ->arrayNode('address')
                                ->children()
                                    ->scalarNode('street')->isRequired()->cannotBeEmpty()->end()
                                    ->scalarNode('postcode')->isRequired()->cannotBeEmpty()->end()
                                    ->scalarNode('city')->isRequired()->cannotBeEmpty()->end()
                                    ->scalarNode('country_code')->defaultValue('FR')->end()
                                    ->scalarNode('phone_number')->defaultNull()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /** @param AddressOptions $options */
    private function address(Customer $customer, array $options): AddressInterface
    {
        $address = $this->addressFactory->createNew();
        $address->setFirstName($customer->getFirstName());
        $address->setLastName($customer->getLastName());
        $address->setCompany($customer->getCompany());
        $address->setPhoneNumber($options['phone_number'] ?? $customer->getPhoneNumber());
        $address->setStreet($options['street']);
        $address->setPostcode($options['postcode']);
        $address->setCity($options['city']);
        $address->setCountryCode($options['country_code']);
        $address->setCustomer($customer);

        return $address;
    }
}
