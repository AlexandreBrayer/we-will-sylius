<?php

declare(strict_types=1);

namespace App\Yack\Account\Application\Registration;

use App\Entity\Customer\Customer;
use App\Yack\Account\Domain\CustomerProfile;
use App\Yack\Account\Domain\Siret;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Creates the customer account at the end of the Yack sign-up.
 *
 * - group "pro" or "ecom" depending on the chosen profile, company / SIRET / VAT number;
 * - billing address = default address, other delivery address added to the address book;
 * - shop user created **disabled**: Sylius' "sylius.customer.post_register" listener sends the
 *   activation e-mail (channel with account verification), the link enables the account.
 */
final readonly class RegisterCustomer
{
    public const POST_REGISTER_EVENT = 'sylius.customer.post_register';

    /**
     * @param FactoryInterface<Customer> $customerFactory
     * @param FactoryInterface<ShopUserInterface> $shopUserFactory
     * @param FactoryInterface<AddressInterface> $addressFactory
     * @param RepositoryInterface<Customer> $customerRepository
     * @param RepositoryInterface<CustomerGroupInterface> $customerGroupRepository
     */
    public function __construct(
        #[Autowire(service: 'sylius.factory.customer')]
        private FactoryInterface $customerFactory,
        #[Autowire(service: 'sylius.factory.shop_user')]
        private FactoryInterface $shopUserFactory,
        #[Autowire(service: 'sylius.factory.address')]
        private FactoryInterface $addressFactory,
        #[Autowire(service: 'sylius.repository.customer')]
        private RepositoryInterface $customerRepository,
        #[Autowire(service: 'sylius.repository.customer_group')]
        private RepositoryInterface $customerGroupRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function isEmailTaken(string $email): bool
    {
        $customer = $this->findCustomer($email);

        return null !== $customer && null !== $customer->getUser();
    }

    public function hashPassword(string $plainPassword): string
    {
        return $this->passwordHasher->hashPassword($this->shopUserFactory->createNew(), $plainPassword);
    }

    public function __invoke(RegistrationData $data): Customer
    {
        $email = (string) $data->email;
        if ($this->isEmailTaken($email)) {
            throw new \DomainException(sprintf('An account already exists for "%s".', $email));
        }

        $groupCode = $data->isPro() ? CustomerProfile::PRO_GROUP_CODE : CustomerProfile::ECOM_GROUP_CODE;
        $group = $this->customerGroupRepository->findOneBy(['code' => $groupCode]);
        if (!$group instanceof CustomerGroupInterface) {
            throw new \LogicException(sprintf('Customer group "%s" does not exist.', $groupCode));
        }

        // A guest who already ordered has a customer without user: reuse it.
        $customer = $this->findCustomer($email) ?? $this->customerFactory->createNew();
        $customer->setEmail($email);
        $customer->setFirstName($data->firstName);
        $customer->setLastName($data->lastName);
        $customer->setPhoneNumber($data->phoneNumber);
        $customer->setGroup($group);
        $customer->setCompany($data->company);
        $customer->setSiret(null !== $data->siret && '' !== $data->siret ? Siret::normalize($data->siret) : null);
        $customer->setVatNumber(null !== $data->vatNumber && '' !== $data->vatNumber ? strtoupper(Siret::normalize($data->vatNumber)) : null);

        $customer->setDefaultAddress($this->address($customer, $data->billingStreet, $data->billingPostcode, $data->billingCity, $data->billingCountryCode));
        if (!$data->shippingSameAsBilling) {
            $customer->addAddress($this->address($customer, $data->shippingStreet, $data->shippingPostcode, $data->shippingCity, $data->shippingCountryCode));
        }

        $user = $this->shopUserFactory->createNew();
        $user->setPassword($data->passwordHash);
        $user->setEnabled(false);
        $customer->setUser($user);

        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new GenericEvent($customer), self::POST_REGISTER_EVENT);

        return $customer;
    }

    private function findCustomer(string $email): ?Customer
    {
        return $this->customerRepository->findOneBy(['emailCanonical' => mb_strtolower(trim($email))]);
    }

    private function address(Customer $customer, ?string $street, ?string $postcode, ?string $city, ?string $countryCode): AddressInterface
    {
        $address = $this->addressFactory->createNew();
        $address->setFirstName($customer->getFirstName());
        $address->setLastName($customer->getLastName());
        $address->setCompany($customer->getCompany());
        $address->setPhoneNumber($customer->getPhoneNumber());
        $address->setStreet($street);
        $address->setPostcode($postcode);
        $address->setCity($city);
        $address->setCountryCode($countryCode);

        return $address;
    }
}
