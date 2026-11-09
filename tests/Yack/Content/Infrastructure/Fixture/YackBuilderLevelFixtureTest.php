<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Fixture;

use App\Entity\User\AdminUser;
use App\Yack\Content\Domain\BuilderLevel;
use App\Yack\Content\Infrastructure\Fixture\YackBuilderLevelFixture;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * @internal
 */
#[CoversClass(YackBuilderLevelFixture::class)]
final class YackBuilderLevelFixtureTest extends TestCase
{
    public function testItSetsTheLevelOfEachAdministratorByEmail(): void
    {
        $admin = new AdminUser();
        $editor = new AdminUser();
        /** @var UserRepositoryInterface<AdminUserInterface>&\PHPUnit\Framework\MockObject\Stub $administrators */
        $administrators = $this->createStub(UserRepositoryInterface::class);
        $administrators->method('findOneByEmail')->willReturnMap([
            ['admin@yack.test', $admin],
            ['editeur@yack.test', $editor],
        ]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');
        $fixture = new YackBuilderLevelFixture($administrators, $entityManager);

        $fixture->load(self::options($fixture, ['Admin@Yack.test' => 'libre', 'editeur@yack.test' => 'charte']));

        $this->assertSame('yack_builder_level', $fixture->getName());
        $this->assertSame(BuilderLevel::Libre, $admin->getBuilderLevel());
        $this->assertSame(BuilderLevel::Charte, $editor->getBuilderLevel());
    }

    public function testAnUnknownAdministratorStopsTheLoading(): void
    {
        $fixture = new YackBuilderLevelFixture($this->createStub(UserRepositoryInterface::class), $this->createStub(EntityManagerInterface::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Administrator "personne@yack.test" not found');

        $fixture->load(self::options($fixture, ['personne@yack.test' => 'libre']));
    }

    public function testAnUnknownLevelIsRefusedByTheConfiguration(): void
    {
        $fixture = new YackBuilderLevelFixture($this->createStub(UserRepositoryInterface::class), $this->createStub(EntityManagerInterface::class));

        $this->expectException(InvalidConfigurationException::class);

        self::options($fixture, ['admin@yack.test' => 'total']);
    }

    /**
     * @param array<string, string> $administrators
     *
     * @return array{administrators: array<string, string>}
     */
    private static function options(YackBuilderLevelFixture $fixture, array $administrators): array
    {
        /** @var array{administrators: array<string, string>} $options */
        $options = (new Processor())->processConfiguration($fixture, [['administrators' => $administrators]]);

        return $options;
    }
}
