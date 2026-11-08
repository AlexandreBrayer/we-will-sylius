<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Get;
use App\Entity\User\AdminUser;
use App\Entity\User\ShopUser;
use App\Yack\Content\Domain\BuilderLevel;
use App\Yack\Content\Domain\Charte;
use App\Yack\Content\Infrastructure\Api\BuilderMeProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @internal
 */
#[CoversClass(BuilderMeProvider::class)]
final class BuilderMeProviderTest extends TestCase
{
    public function testTheLoggedInAdministratorComesWithTheirLevelAndTheCharte(): void
    {
        $admin = new AdminUser();
        $admin->setFirstName('Éditeur');
        $admin->setBuilderLevel(BuilderLevel::Charte);

        $me = $this->provider($admin)->provide(new Get());

        $this->assertSame('charte', $me->level);
        $this->assertSame('Charte', $me->levelLabel);
        $this->assertSame('Éditeur', $me->firstName);
        $this->assertSame(Charte::toArray(), $me->charte);
    }

    public function testWithoutAnAdministratorAccessIsDenied(): void
    {
        $this->expectException(AccessDeniedException::class);

        (new BuilderMeProvider(new TokenStorage()))->provide(new Get());
    }

    public function testACustomerIsNotAnAdministrator(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->provider(new ShopUser())->provide(new Get());
    }

    private function provider(UserInterface $user): BuilderMeProvider
    {
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken($user, 'api_admin', ['ROLE_API_ACCESS']));

        return new BuilderMeProvider($tokens);
    }
}
