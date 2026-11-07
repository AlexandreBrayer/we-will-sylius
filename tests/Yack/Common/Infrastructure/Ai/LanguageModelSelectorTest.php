<?php

declare(strict_types=1);

namespace App\Tests\Yack\Common\Infrastructure\Ai;

use App\Yack\Common\Domain\AiInput;
use App\Yack\Common\Domain\AiMisconfigured;
use App\Yack\Common\Domain\LanguageModel;
use App\Yack\Common\Infrastructure\Ai\LanguageModelSelector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LanguageModelSelector::class)]
final class LanguageModelSelectorTest extends TestCase
{
    public function testMistralIsUsedWhenSelected(): void
    {
        $selector = new LanguageModelSelector(self::model('anthropic'), self::model('mistral'), 'mistral');

        $this->assertSame(['by' => 'mistral'], $selector->extract('p', 'i', [AiInput::text('q')], []));
    }

    public function testAnthropicIsUsedWhenSelected(): void
    {
        $selector = new LanguageModelSelector(self::model('anthropic'), self::model('mistral'), 'anthropic');

        $this->assertSame(['by' => 'anthropic'], $selector->extract('p', 'i', [AiInput::text('q')], []));
    }

    public function testFakeModeRefusesAnyRealCall(): void
    {
        $selector = new LanguageModelSelector(self::model('anthropic'), self::model('mistral'), null);

        $this->expectException(AiMisconfigured::class);
        $selector->extract('p', 'i', [AiInput::text('q')], []);
    }

    private static function model(string $name): LanguageModel
    {
        return new class($name) implements LanguageModel {
            public function __construct(private readonly string $name)
            {
            }

            public function extract(string $purpose, string $instructions, array $inputs, array $schema): array
            {
                return ['by' => $this->name];
            }
        };
    }
}
