<?php

declare(strict_types=1);

namespace App\Tests\Yack\Assistance\Infrastructure\Api;

use App\Yack\Assistance\Infrastructure\Api\AssistanceSwitch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Needs Symfony (vendor/) : PHPUnit seulement.
 *
 * @internal
 */
#[CoversClass(AssistanceSwitch::class)]
final class AssistanceSwitchTest extends TestCase
{
    public function testAnEnabledModuleLetsTheRequestThrough(): void
    {
        $switch = new AssistanceSwitch(true);

        $switch->assertEnabled();
        $this->assertTrue($switch->isEnabled());
    }

    public function testADisabledModuleAnswersNotFound(): void
    {
        $switch = new AssistanceSwitch(false);
        $this->assertFalse($switch->isEnabled());

        $this->expectException(NotFoundHttpException::class);
        $switch->assertEnabled();
    }
}
