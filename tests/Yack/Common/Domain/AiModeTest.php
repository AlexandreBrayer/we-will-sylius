<?php

declare(strict_types=1);

namespace App\Tests\Yack\Common\Domain;

use App\Yack\Common\Domain\AiMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AiMode::class)]
final class AiModeTest extends TestCase
{
    #[DataProvider('provideValues')]
    public function testFromEnv(?string $value, AiMode $expected, bool $real): void
    {
        $mode = AiMode::fromEnv($value);

        $this->assertSame($expected, $mode);
        $this->assertSame($real, $mode->isReal());
    }

    /**
     * @return \Iterator<string, array{string|null, AiMode, bool}>
     */
    public static function provideValues(): \Iterator
    {
        yield 'absent' => [null, AiMode::Fake, false];
        yield 'empty' => ['', AiMode::Fake, false];
        yield 'fake' => ['fake', AiMode::Fake, false];
        yield 'anthropic' => ['anthropic', AiMode::Anthropic, true];
        yield 'case and spaces' => [' Anthropic ', AiMode::Anthropic, true];
        yield 'unknown' => ['openai', AiMode::Fake, false];
    }
}
