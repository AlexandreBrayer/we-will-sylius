<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Fixture;

use App\Entity\Customer\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/**
 * Sets the company identity (raison sociale, SIRET) of seeded professional customers; the
 * stock shop_user fixture has no such fields.
 */
final class YackCustomerCompanyFixture extends AbstractFixture
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function getName(): string
    {
        return 'yack_customer_company';
    }

    /** @param array{customers: array<string, array{company: string, siret: string}>} $options */
    public function load(array $options): void
    {
        foreach ($options['customers'] as $email => $identity) {
            $customer = $this->entityManager->getRepository(Customer::class)->findOneBy(['emailCanonical' => mb_strtolower($email)]);
            if (!$customer instanceof Customer) {
                throw new \InvalidArgumentException(sprintf('Customer "%s" not found: load the shop_user fixture first.', $email));
            }

            $customer->setCompany($identity['company']);
            $customer->setSiret($identity['siret']);
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
                            ->scalarNode('company')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('siret')->isRequired()->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
