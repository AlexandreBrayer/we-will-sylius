<?php

declare(strict_types=1);

namespace App\Tests\Yack\Account\Infrastructure\Fixture;

use App\Entity\Addressing\Address;
use App\Entity\Customer\Customer;
use App\Entity\User\ShopUser;
use App\Yack\Account\Domain\Siret;
use App\Yack\Account\Infrastructure\Fixture\YackDemoCustomerFixture;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
#[CoversClass(YackDemoCustomerFixture::class)]
final class YackDemoCustomerFixtureTest extends TestCase
{
    private const NOW = '2026-11-09 10:30:00';

    /** @var list<array<string, mixed>> */
    private array $created = [];

    public function testItCreatesEachCustomerWithItsAccountCompanyAddressAndRegistrationDate(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->exactly(2))->method('persist');
        $entityManager->expects($this->once())->method('flush');
        $fixture = $this->fixture($entityManager, $this->createStub(CustomerRepositoryInterface::class));

        $fixture->load(self::options($fixture, ['customers' => [[
            'email' => 'lucas.morvan@exemple.test',
            'first_name' => 'Lucas',
            'last_name' => 'Morvan',
            'phone_number' => '01 99 00 01 01',
            'group' => 'ecom',
            'company' => 'Atelier Thermique Morvan',
            'siret' => '00000101600013',
            'vat_number' => 'FR53000001016',
            'registered_days_ago' => 30,
            'address' => ['street' => '14 rue des Artisans', 'postcode' => '21000', 'city' => 'Dijon'],
        ]]]));

        $this->assertSame('yack_demo_customer', $fixture->getName());
        $this->assertCount(1, $this->created);
        $options = $this->created[0]['options'];
        $this->assertSame(['lucas.morvan@exemple.test', 'yack', 'ecom', true, null], [$options['email'], $options['password'], $options['customer_group'], $options['enabled'], $options['birthday']]);

        /** @var Customer $customer */
        $customer = $this->created[0]['customer'];
        $this->assertSame('Atelier Thermique Morvan', $customer->getCompany());
        $this->assertSame('00000101600013', $customer->getSiret());
        $this->assertSame('FR53000001016', $customer->getVatNumber());
        $address = $customer->getDefaultAddress();
        $this->assertInstanceOf(AddressInterface::class, $address);
        $this->assertSame(['Lucas', 'Morvan', 'Atelier Thermique Morvan', '01 99 00 01 01', '14 rue des Artisans', '21000', 'Dijon', 'FR'], [
            $address->getFirstName(), $address->getLastName(), $address->getCompany(), $address->getPhoneNumber(),
            $address->getStreet(), $address->getPostcode(), $address->getCity(), $address->getCountryCode(),
        ]);
        $this->assertSame($customer, $address->getCustomer());

        $expected = YackDemoCustomerFixture::registeredAt(new \DateTimeImmutable(self::NOW), 30, 'lucas.morvan@exemple.test');
        $this->assertSame('2026-10-10', $expected->format('Y-m-d'));
        $this->assertSame($expected->format('c'), $customer->getCreatedAt()?->format('c'));
        $this->assertSame($expected->format('c'), $customer->getUser()?->getCreatedAt()?->format('c'));
    }

    public function testTheAccountsOfTheStockFixtureAreBackdated(): void
    {
        $customer = new Customer();
        $user = new ShopUser();
        $user->setCustomer($customer);
        /** @var CustomerRepositoryInterface<CustomerInterface>&\PHPUnit\Framework\MockObject\Stub $customers */
        $customers = $this->createStub(CustomerRepositoryInterface::class);
        $customers->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?Customer => 'ecom@yack.test' === $criteria['emailCanonical'] ? $customer : null);
        $fixture = $this->fixture($this->createStub(EntityManagerInterface::class), $customers);

        $fixture->load(self::options($fixture, ['registered_days_ago' => ['Ecom@Yack.test' => 700]]));

        $this->assertSame('2024-12-09', $customer->getCreatedAt()?->format('Y-m-d'));
        $this->assertSame($customer->getCreatedAt()?->format('c'), $user->getCreatedAt()?->format('c'));
        $this->assertSame([], $this->created);
    }

    public function testAnUnknownAccountToBackdateStopsTheLoading(): void
    {
        $fixture = $this->fixture($this->createStub(EntityManagerInterface::class), $this->createStub(CustomerRepositoryInterface::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer "personne@yack.test" not found');

        $fixture->load(self::options($fixture, ['registered_days_ago' => ['personne@yack.test' => 10]]));
    }

    public function testAFactoryWithoutYackCustomerIsRefused(): void
    {
        $factory = $this->createStub(ExampleFactoryInterface::class);
        $factory->method('create')->willReturn(new ShopUser());
        $fixture = new YackDemoCustomerFixture($this->createStub(EntityManagerInterface::class), $this->createStub(CustomerRepositoryInterface::class), $factory, $this->addressFactory(), new MockClock(self::NOW));

        $this->expectException(\LogicException::class);

        $fixture->load(self::options($fixture, ['customers' => [self::minimal('a@exemple.test')]]));
    }

    public function testAnUnknownGroupIsRefusedByTheConfiguration(): void
    {
        $fixture = $this->fixture($this->createStub(EntityManagerInterface::class), $this->createStub(CustomerRepositoryInterface::class));

        $this->expectException(InvalidConfigurationException::class);

        self::options($fixture, ['customers' => [['group' => 'vip'] + self::minimal('a@exemple.test')]]);
    }

    public function testTheRegistrationTimeDependsOnTheDayOnly(): void
    {
        $morning = YackDemoCustomerFixture::registeredAt(new \DateTimeImmutable('2026-11-09 00:05:00'), 12, 'x@exemple.test');
        $evening = YackDemoCustomerFixture::registeredAt(new \DateTimeImmutable('2026-11-09 23:55:00'), 12, 'x@exemple.test');

        $this->assertSame($morning->format('c'), $evening->format('c'));
        $this->assertSame('2026-10-28', $morning->format('Y-m-d'));
        $this->assertGreaterThanOrEqual(8, (int) $morning->format('G'));
        $this->assertLessThanOrEqual(18, (int) $morning->format('G'));
    }

    public function testTheDemoCustomersOfTheSuiteAreFictitiousAndVaried(): void
    {
        /** @var array{sylius_fixtures: array{suites: array{yack: array{fixtures: array{yack_demo_customer: array{options: array<string, mixed>}}}}}} $yaml */
        $yaml = Yaml::parseFile(\dirname(__DIR__, 5) . '/config/yack/fixtures.yaml');
        $fixture = $this->fixture($this->createStub(EntityManagerInterface::class), $this->createStub(CustomerRepositoryInterface::class));
        /** @var array{password: string, registered_days_ago: array<string, int>, customers: list<array{email: string, phone_number: ?string, group: string, company: ?string, siret: ?string, vat_number: ?string, registered_days_ago: int}>} $options */
        $options = self::options($fixture, $yaml['sylius_fixtures']['suites']['yack']['fixtures']['yack_demo_customer']['options']);

        $this->assertSame('yack', $options['password']);
        $this->assertGreaterThanOrEqual(20, \count($options['customers']));
        $groups = ['ecom-company' => 0, 'ecom-individual' => 0, 'pro' => 0];
        $emails = [];
        foreach ($options['customers'] as $customer) {
            $this->assertMatchesRegularExpression('/^[a-z.]+@exemple\.test$/', $customer['email']);
            $this->assertStringStartsWith('01 99 00 ', (string) $customer['phone_number'], 'Plage de numéros réservée à la fiction');
            if (null !== $customer['siret']) {
                $this->assertStringStartsWith('00000', $customer['siret'], 'SIRET manifestement fictif');
                $this->assertTrue(Siret::isValid($customer['siret']), $customer['siret']);
            }
            if (null !== $customer['vat_number']) {
                $this->assertTrue(Siret::isValidFrenchVatNumber($customer['vat_number'], $customer['siret']), $customer['vat_number']);
            }
            ++$groups['pro' === $customer['group'] ? 'pro' : (null === $customer['company'] ? 'ecom-individual' : 'ecom-company')];
            $emails[] = $customer['email'];
        }
        $this->assertSame($emails, array_unique($emails));
        $this->assertGreaterThanOrEqual(10, $groups['ecom-company']);
        $this->assertGreaterThanOrEqual(3, $groups['ecom-individual']);
        $this->assertGreaterThanOrEqual(3, $groups['pro']);
        $this->assertSame(['ecom@yack.test', 'pro@yack.test', 'pro2@yack.test'], array_keys($options['registered_days_ago']));
    }

    /**
     * @param CustomerRepositoryInterface<CustomerInterface> $customers
     */
    private function fixture(EntityManagerInterface $entityManager, CustomerRepositoryInterface $customers): YackDemoCustomerFixture
    {
        $factory = $this->createStub(ExampleFactoryInterface::class);
        $factory->method('create')->willReturnCallback(function (array $options): ShopUser {
            $customer = new Customer();
            $customer->setEmail($options['email']);
            $customer->setFirstName($options['first_name']);
            $customer->setLastName($options['last_name']);
            $customer->setPhoneNumber($options['phone_number']);
            $user = new ShopUser();
            $user->setCustomer($customer);
            $this->created[] = ['options' => $options, 'customer' => $customer];

            return $user;
        });

        return new YackDemoCustomerFixture($entityManager, $customers, $factory, $this->addressFactory(), new MockClock(self::NOW));
    }

    /** @return FactoryInterface<AddressInterface> */
    private function addressFactory(): FactoryInterface
    {
        /** @var FactoryInterface<AddressInterface>&\PHPUnit\Framework\MockObject\Stub $factory */
        $factory = $this->createStub(FactoryInterface::class);
        $factory->method('createNew')->willReturnCallback(static fn (): Address => new Address());

        return $factory;
    }

    /** @return array<string, mixed> */
    private static function minimal(string $email): array
    {
        return [
            'email' => $email, 'first_name' => 'A', 'last_name' => 'B', 'group' => 'ecom', 'registered_days_ago' => 1,
            'address' => ['street' => '1 rue', 'postcode' => '75001', 'city' => 'Paris'],
        ];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private static function options(YackDemoCustomerFixture $fixture, array $options): array
    {
        /** @var array<string, mixed> $processed */
        $processed = (new Processor())->processConfiguration($fixture, [$options]);

        return $processed;
    }
}
