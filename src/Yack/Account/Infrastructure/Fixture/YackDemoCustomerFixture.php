<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Fixture;

use App\Entity\Customer\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Demo customers (companies and individuals) with their account, company identity, default
 * address and a registration date in the past, so that the back-office dashboard shows new
 * customers over the months. Also backdates the accounts of the stock shop_user fixture.
 * Everything comes from the options (no Faker): the same customers at every load, dates
 * relative to the loading day.
 *
 * @phpstan-type AddressOptions array{street: string, postcode: string, city: string, country_code: string}
 * @phpstan-type CustomerOptions array{
 *     email: string, first_name: string, last_name: string, phone_number: ?string, group: string,
 *     company: ?string, siret: ?string, vat_number: ?string, registered_days_ago: int, address: AddressOptions
 * }
 * @phpstan-type Options array{password: string, registered_days_ago: array<string, int>, customers: list<CustomerOptions>}
 */
final class YackDemoCustomerFixture extends AbstractFixture
{
    /**
     * @param CustomerRepositoryInterface<CustomerInterface> $customers
     * @param ExampleFactoryInterface<ShopUserInterface>     $shopUserFactory
     * @param FactoryInterface<AddressInterface>             $addressFactory
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'sylius.repository.customer')]
        private readonly CustomerRepositoryInterface $customers,
        #[Autowire(service: 'sylius.fixture.example_factory.shop_user')]
        private readonly ExampleFactoryInterface $shopUserFactory,
        #[Autowire(service: 'sylius.factory.address')]
        private readonly FactoryInterface $addressFactory,
        private readonly ClockInterface $clock,
    ) {
    }

    public function getName(): string
    {
        return 'yack_demo_customer';
    }

    /** @param Options $options */
    public function load(array $options): void
    {
        $now = $this->clock->now();

        foreach ($options['registered_days_ago'] as $email => $days) {
            $customer = $this->customers->findOneBy(['emailCanonical' => mb_strtolower($email)]);
            if (!$customer instanceof Customer) {
                throw new \InvalidArgumentException(sprintf('Customer "%s" not found: load the shop_user fixture first.', $email));
            }
            self::register($customer, self::registeredAt($now, $days, $email));
        }

        foreach ($options['customers'] as $entry) {
            $user = $this->shopUserFactory->create([
                'email' => $entry['email'],
                'first_name' => $entry['first_name'],
                'last_name' => $entry['last_name'],
                'phone_number' => $entry['phone_number'],
                'password' => $options['password'],
                'customer_group' => $entry['group'],
                'enabled' => true,
                'birthday' => null,
            ]);
            if (!($customer = $user->getCustomer()) instanceof Customer) {
                throw new \LogicException('The shop_user example factory must create a shop user with a Yack customer.');
            }

            $customer->setCompany($entry['company']);
            $customer->setSiret($entry['siret']);
            $customer->setVatNumber($entry['vat_number']);
            $customer->setDefaultAddress($this->address($customer, $entry['address']));
            self::register($customer, self::registeredAt($now, $entry['registered_days_ago'], $entry['email']));

            $this->entityManager->persist($customer);
            $this->entityManager->persist($user);
        }

        $this->entityManager->flush();
    }

    /**
     * Registration date: N days before the loading day, at a time of day derived from the e-mail
     * (between 8:00 and 18:59), so that two loads the same day give the same hour.
     */
    public static function registeredAt(\DateTimeImmutable $now, int $daysAgo, string $email): \DateTimeImmutable
    {
        $minutes = 480 + crc32(mb_strtolower($email)) % 660;

        return $now->setTime(0, 0)->modify(sprintf('-%d days +%d minutes', $daysAgo, $minutes));
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->scalarNode('password')->defaultValue('yack')->cannotBeEmpty()->end()
                ->arrayNode('registered_days_ago')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('email')
                    ->integerPrototype()->min(0)->end()
                ->end()
                ->arrayNode('customers')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('email')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('first_name')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('last_name')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('phone_number')->defaultNull()->end()
                            ->enumNode('group')->values(['pro', 'ecom'])->isRequired()->end()
                            ->scalarNode('company')->defaultNull()->end()
                            ->scalarNode('siret')->defaultNull()->end()
                            ->scalarNode('vat_number')->defaultNull()->end()
                            ->integerNode('registered_days_ago')->min(0)->isRequired()->end()
                            ->arrayNode('address')
                                ->isRequired()
                                ->children()
                                    ->scalarNode('street')->isRequired()->cannotBeEmpty()->end()
                                    ->scalarNode('postcode')->isRequired()->cannotBeEmpty()->end()
                                    ->scalarNode('city')->isRequired()->cannotBeEmpty()->end()
                                    ->scalarNode('country_code')->defaultValue('FR')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private static function register(Customer $customer, \DateTimeImmutable $registeredAt): void
    {
        $customer->setCreatedAt(\DateTime::createFromImmutable($registeredAt));
        $customer->getUser()?->setCreatedAt(\DateTime::createFromImmutable($registeredAt));
    }

    /** @param AddressOptions $options */
    private function address(Customer $customer, array $options): AddressInterface
    {
        $address = $this->addressFactory->createNew();
        $address->setFirstName($customer->getFirstName());
        $address->setLastName($customer->getLastName());
        $address->setCompany($customer->getCompany());
        $address->setPhoneNumber($customer->getPhoneNumber());
        $address->setStreet($options['street']);
        $address->setPostcode($options['postcode']);
        $address->setCity($options['city']);
        $address->setCountryCode($options['country_code']);
        $address->setCustomer($customer);

        return $address;
    }
}
