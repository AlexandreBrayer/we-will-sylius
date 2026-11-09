<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Fixture;

use App\Entity\User\AdminUser;
use App\Yack\Content\Domain\BuilderLevel;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Sets the builder level (BuilderLevel) of seeded administrators, by e-mail; the stock admin_user
 * fixture has no such field. An administrator left out keeps the default level, `charte`.
 */
final class YackBuilderLevelFixture extends AbstractFixture
{
    /** @param UserRepositoryInterface<AdminUserInterface> $administrators */
    public function __construct(
        #[Autowire(service: 'sylius.repository.admin_user')]
        private readonly UserRepositoryInterface $administrators,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getName(): string
    {
        return 'yack_builder_level';
    }

    /** @param array{administrators: array<string, string>} $options */
    public function load(array $options): void
    {
        foreach ($options['administrators'] as $email => $level) {
            $admin = $this->administrators->findOneByEmail(mb_strtolower((string) $email));
            if (!$admin instanceof AdminUser) {
                throw new \InvalidArgumentException(sprintf('Administrator "%s" not found: load the admin_user fixture first.', $email));
            }

            $admin->setBuilderLevel(BuilderLevel::from($level));
        }

        $this->entityManager->flush();
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->arrayNode('administrators')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('email')
                    ->enumPrototype()
                        ->values(array_map(static fn (BuilderLevel $level): string => $level->value, BuilderLevel::cases()))
                    ->end()
                ->end()
            ->end()
        ;
    }
}
