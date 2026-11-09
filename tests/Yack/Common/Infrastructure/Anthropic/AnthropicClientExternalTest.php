<?php

declare(strict_types=1);

namespace App\Tests\Yack\Common\Infrastructure\Anthropic;

use App\Yack\Common\Domain\AiInput;
use App\Yack\Common\Infrastructure\Anthropic\AiUsageGuard;
use App\Yack\Common\Infrastructure\Anthropic\AnthropicClient;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Real call to the Anthropic API: needs ANTHROPIC_API_KEY and YACK_AI_MODEL in .env.local.
 *
 * @internal
 */
#[Group('external')]
final class AnthropicClientExternalTest extends TestCase
{
    public function testStructuredExtraction(): void
    {
        $key = $_SERVER['ANTHROPIC_API_KEY'] ?? $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY');
        $model = $_SERVER['YACK_AI_MODEL'] ?? $_ENV['YACK_AI_MODEL'] ?? getenv('YACK_AI_MODEL');
        if (!\is_string($key) || '' === $key || !\is_string($model) || '' === $model) {
            $this->markTestSkipped('ANTHROPIC_API_KEY et YACK_AI_MODEL absents.');
        }

        $client = new AnthropicClient(HttpClient::create(), new AiUsageGuard(new ArrayAdapter()), $key, $model);
        $answer = $client->extract(
            'test.external',
            'Extrais la puissance en kW de la phrase.',
            [AiInput::text('Une PAC air/eau de 8 kW pour un plancher chauffant.')],
            ['type' => 'object', 'properties' => ['kw' => ['type' => 'number']], 'required' => ['kw']],
        );

        $this->assertSame(8, (int) $answer['kw']);
    }
}
