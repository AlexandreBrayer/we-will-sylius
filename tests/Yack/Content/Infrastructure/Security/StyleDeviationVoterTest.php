<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Security;

use App\Entity\User\AdminUser;
use App\Yack\Content\Domain\BuilderLevel;
use App\Yack\Content\Infrastructure\Security\StyleDeviationVoter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * @internal
 */
#[CoversClass(StyleDeviationVoter::class)]
final class StyleDeviationVoterTest extends TestCase
{
    #[DataProvider('provideLevels')]
    public function testOnlyALibreAdministratorOpensTheReport(BuilderLevel $level, int $vote): void
    {
        $admin = new AdminUser();
        $admin->setBuilderLevel($level);

        $this->assertSame($vote, (new StyleDeviationVoter())->vote(new UsernamePasswordToken($admin, 'admin', ['ROLE_ADMINISTRATION_ACCESS']), null, [StyleDeviationVoter::ATTRIBUTE]));
    }

    /**
     * @return \Iterator<string, array{BuilderLevel, int}>
     */
    public static function provideLevels(): \Iterator
    {
        yield 'libre' => [BuilderLevel::Libre, VoterInterface::ACCESS_GRANTED];
        yield 'libre avec avertissement' => [BuilderLevel::LibreAvertissement, VoterInterface::ACCESS_DENIED];
        yield 'charte' => [BuilderLevel::Charte, VoterInterface::ACCESS_DENIED];
    }

    #[DataProvider('provideOtherTokens')]
    public function testWithoutAnAdministratorTheReportIsRefused(TokenInterface $token): void
    {
        $this->assertSame(VoterInterface::ACCESS_DENIED, (new StyleDeviationVoter())->vote($token, null, [StyleDeviationVoter::ATTRIBUTE]));
    }

    /**
     * @return \Iterator<string, array{TokenInterface}>
     */
    public static function provideOtherTokens(): \Iterator
    {
        yield 'anonymous' => [new NullToken()];
        yield 'another kind of user' => [new UsernamePasswordToken(new InMemoryUser('client', null, ['ROLE_USER']), 'shop', ['ROLE_USER'])];
    }

    public function testOtherAttributesAreLeftToOtherVoters(): void
    {
        $admin = new AdminUser();
        $admin->setBuilderLevel(BuilderLevel::Libre);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, (new StyleDeviationVoter())->vote(new UsernamePasswordToken($admin, 'admin'), null, ['ROLE_ADMIN']));
    }
}
