<?php

declare(strict_types=1);

namespace App\Tests\Yack\Common\Domain;

use App\Yack\Common\Domain\AiMisconfigured;
use App\Yack\Common\Domain\AiUnavailable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AiUnavailable::class)]
#[CoversClass(AiMisconfigured::class)]
final class AiUnavailableTest extends TestCase
{
    public function testMessages(): void
    {
        $this->assertSame('Service IA indisponible : trop de demandes', AiUnavailable::because('trop de demandes')->getMessage());
        $this->assertStringContainsString('YACK_AI_MODEL est vide', AiMisconfigured::missing('YACK_AI_MODEL')->getMessage());
    }
}
