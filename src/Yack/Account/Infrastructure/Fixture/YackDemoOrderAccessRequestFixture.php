<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Fixture;

use App\Entity\Account\OrderAccessRequest;
use App\Entity\Customer\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/**
 * Pending demo requests to open online ordering, filed N days before the load by seeded PRO customers: the identity
 * of the request comes from the customer (company, SIRET, name, phone, default address), like the shop form.
 *
 * @phpstan-type RequestOptions array{email: string, days_ago: int, message: ?string}
 */
final class YackDemoOrderAccessRequestFixture extends AbstractFixture
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    public function getName(): string
    {
        return 'yack_demo_order_access_request';
    }

    /** @param array{requests: list<RequestOptions>} $options */
    public function load(array $options): void
    {
        $today = $this->clock->now()->setTime(0, 0);
        foreach ($options['requests'] as $index => $request) {
            $customer = $this->entityManager->getRepository(Customer::class)->findOneBy(['emailCanonical' => mb_strtolower($request['email'])]);
            if (!$customer instanceof Customer) {
                throw new \InvalidArgumentException(sprintf('Customer "%s" not found: load the demo customers first.', $request['email']));
            }
            $address = $customer->getDefaultAddress();

            $this->entityManager->persist(new OrderAccessRequest(
                $customer,
                (string) ($customer->getCompany() ?? $address?->getCompany()),
                (string) $customer->getSiret(),
                trim(sprintf('%s %s', $customer->getFirstName() ?? '', $customer->getLastName() ?? '')),
                (string) $customer->getEmail(),
                (string) ($customer->getPhoneNumber() ?? $address?->getPhoneNumber()),
                null === $address ? '' : sprintf("%s\n%s %s", $address->getStreet(), $address->getPostcode(), $address->getCity()),
                $request['message'],
                $today->modify(sprintf('-%d days +%d minutes', $request['days_ago'], 540 + 37 * $index)),
            ));
        }

        $this->entityManager->flush();
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->arrayNode('requests')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('email')->isRequired()->cannotBeEmpty()->end()
                            ->integerNode('days_ago')->min(0)->defaultValue(1)->end()
                            ->scalarNode('message')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
