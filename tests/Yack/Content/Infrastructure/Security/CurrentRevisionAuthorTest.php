<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Security;

use App\Entity\User\AdminUser;
use App\Yack\Content\Infrastructure\Security\CurrentRevisionAuthor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * @internal
 */
#[CoversClass(CurrentRevisionAuthor::class)]
final class CurrentRevisionAuthorTest extends TestCase
{
    public function testTheAuthorIsTheLoggedInAdministrator(): void
    {
        $admin = new AdminUser();
        $admin->setFirstName('Sophie');
        $admin->setLastName('Martin');
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken($admin, 'api_admin', ['ROLE_API_ACCESS']));

        $this->assertSame('Sophie Martin', (new CurrentRevisionAuthor($tokens))->get()?->name);
    }

    public function testWithoutAdministratorThereIsNoAuthor(): void
    {
        $this->assertNull((new CurrentRevisionAuthor(new TokenStorage()))->get());

        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('client', null), 'shop', []));
        $this->assertNull((new CurrentRevisionAuthor($tokens))->get());
    }
}
