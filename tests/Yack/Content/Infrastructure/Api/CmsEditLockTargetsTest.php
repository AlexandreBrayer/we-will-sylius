<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use App\Yack\Content\Infrastructure\Api\CmsEditLockTargets;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * @internal
 */
#[CoversClass(CmsEditLockTargets::class)]
final class CmsEditLockTargetsTest extends TestCase
{
    public function testAPageByItsSlugAndATemplateByItsId(): void
    {
        $api = new CmsEditLockApi();

        $this->assertSame($api->page, $api->targets()->target(['slug' => 'offre']));
        $this->assertSame($api->template, $api->targets()->target(['id' => '4']));
        $this->assertSame($api->template, $api->targets()->target(['id' => 4]));
    }

    public function testAnUnknownPageIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Page introuvable.');
        (new CmsEditLockApi())->targets()->target(['slug' => 'inconnue']);
    }

    public function testAnUnknownTemplateIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Template introuvable.');
        (new CmsEditLockApi())->targets()->target(['id' => 'abc']);
    }

    public function testTheAdministratorWhoAsks(): void
    {
        $api = new CmsEditLockApi();
        $this->assertNull($api->targets()->current());

        $api->logIn($api->paul);
        $this->assertSame($api->paul, $api->targets()->current());
        $this->assertSame($api->paul, $api->targets()->admin());
    }

    public function testSomeoneElseIsDenied(): void
    {
        $api = new CmsEditLockApi();
        $api->tokens->setToken(new UsernamePasswordToken(new InMemoryUser('client', null), 'shop', []));
        $this->assertNull($api->targets()->current());

        $this->expectException(AccessDeniedException::class);
        $api->targets()->admin();
    }
}
