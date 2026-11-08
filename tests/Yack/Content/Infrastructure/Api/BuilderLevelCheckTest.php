<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Content\CmsPage;
use App\Entity\User\AdminUser;
use App\Entity\User\ShopUser;
use App\Yack\Content\Domain\BuilderLevel;
use App\Yack\Content\Infrastructure\Api\BuilderLevelCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @internal
 */
#[CoversClass(BuilderLevelCheck::class)]
final class BuilderLevelCheckTest extends TestCase
{
    public function testTheLevelIsTheOneOfTheLoggedInAdministrator(): void
    {
        foreach (BuilderLevel::cases() as $level) {
            $admin = new AdminUser();
            $admin->setBuilderLevel($level);

            $this->assertSame($level, self::check($admin)->level());
        }
    }

    public function testWithoutAnAdministratorTheLevelIsLibre(): void
    {
        $this->assertSame(BuilderLevel::Libre, (new BuilderLevelCheck(new TokenStorage()))->level());
        $this->assertSame(BuilderLevel::Libre, self::check(new ShopUser())->level());
    }

    public function testARefusalIsA422OnBlocksWithTheMessageOfTheDomain(): void
    {
        $page = new CmsPage('offre', 'Offre');

        try {
            self::check(self::admin(BuilderLevel::Charte))->assertAllowed($page, [self::block('#ff7700')]);
            $this->fail('ValidationException expected');
        } catch (ValidationException $exception) {
            $violations = $exception->getConstraintViolationList();
            $this->assertCount(1, $violations);
            $violation = $violations->get(0);
            $this->assertSame('blocks', $violation->getPropertyPath());
            $this->assertSame('Bloc n°1 : la couleur #ff7700 est hors charte. Votre compte ne permet pas de sortir de la charte.', $violation->getMessage());
            $this->assertSame($page, $violation->getRoot());
            $this->assertSame('#ff7700', $violation->getInvalidValue());
        }
    }

    public function testAStoredFreeValuePassesAtTheCharteLevel(): void
    {
        self::check(self::admin(BuilderLevel::Charte))->assertAllowed(new CmsPage(), [self::block('#ff7700')], [self::block('#ff7700')]);
        $this->addToAssertionCount(1);
    }

    public function testAFreeLevelWritesFreeValues(): void
    {
        self::check(self::admin(BuilderLevel::LibreAvertissement))->assertAllowed(new CmsPage(), [self::block('#ff7700')]);
        (new BuilderLevelCheck(new TokenStorage()))->assertAllowed(new CmsPage(), [self::block('#ff7700')]);
        $this->addToAssertionCount(2);
    }

    private static function admin(BuilderLevel $level): AdminUser
    {
        $admin = new AdminUser();
        $admin->setBuilderLevel($level);

        return $admin;
    }

    private static function check(UserInterface $user): BuilderLevelCheck
    {
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken($user, 'api_admin', ['ROLE_API_ACCESS']));

        return new BuilderLevelCheck($tokens);
    }

    /**
     * @return array{type: string, props: array<string, string>, style: array<string, string>}
     */
    private static function block(string $background): array
    {
        return ['type' => 'texte-image', 'props' => [], 'style' => ['fond' => $background]];
    }
}
