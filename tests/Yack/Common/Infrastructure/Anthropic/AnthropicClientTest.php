<?php

declare(strict_types=1);

namespace App\Tests\Yack\Common\Infrastructure\Anthropic;

use App\Yack\Common\Domain\AiInput;
use App\Yack\Common\Domain\AiMisconfigured;
use App\Yack\Common\Domain\AiUnavailable;
use App\Yack\Common\Infrastructure\Anthropic\AiUsageGuard;
use App\Yack\Common\Infrastructure\Anthropic\AnthropicClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 */
#[CoversClass(AnthropicClient::class)]
final class AnthropicClientTest extends TestCase
{
    private const SCHEMA = ['type' => 'object', 'properties' => ['serial' => ['type' => 'string']]];

    public function testStructuredAnswerIsReturned(): void
    {
        $sent = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $sent = ['method' => $method, 'url' => $url, 'headers' => $options['headers'], 'body' => json_decode($options['body'], true)];

            return new MockResponse((string) json_encode(['content' => [
                ['type' => 'text', 'text' => 'ignored'],
                ['type' => 'tool_use', 'name' => 'reponse', 'input' => ['serial' => 'YK24HYD11S000321']],
            ]]));
        });

        $answer = $this->client($http)->extract('sav.nameplate', 'Lis la plaque.', [AiInput::text('photo'), AiInput::image('img', 'image/png')], self::SCHEMA);

        $this->assertSame(['serial' => 'YK24HYD11S000321'], $answer);
        $this->assertSame('POST', $sent['method']);
        $this->assertSame(AnthropicClient::ENDPOINT, $sent['url']);
        $this->assertContains('x-api-key: test-key', $sent['headers']);
        $this->assertSame('model-x', $sent['body']['model']);
        $this->assertSame(['type' => 'tool', 'name' => 'reponse'], $sent['body']['tool_choice']);
        $this->assertSame(self::SCHEMA, $sent['body']['tools'][0]['input_schema']);
        $this->assertSame('image', $sent['body']['messages'][0]['content'][1]['type']);
        $this->assertSame(base64_encode('img'), $sent['body']['messages'][0]['content'][1]['source']['data']);
    }

    public function testPdfIsSentAsDocument(): void
    {
        $payload = $this->client(new MockHttpClient())->payload('i', [AiInput::pdf('%PDF-1.4 x')], self::SCHEMA);

        $this->assertSame('document', $payload['messages'][0]['content'][0]['type']);
        $this->assertSame('application/pdf', $payload['messages'][0]['content'][0]['source']['media_type']);
    }

    #[DataProvider('provideFailures')]
    public function testFailuresBecomeAiUnavailable(int $status, string $message): void
    {
        $client = $this->client(new MockHttpClient(new MockResponse('{}', ['http_code' => $status])));

        $this->expectException(AiUnavailable::class);
        $this->expectExceptionMessage($message);
        $client->extract('p', 'i', [AiInput::text('q')], self::SCHEMA);
    }

    /**
     * @return \Iterator<string, array{int, string}>
     */
    public static function provideFailures(): \Iterator
    {
        yield 'rate limited' => [429, 'trop de demandes'];
        yield 'server error' => [529, 'réponse 529'];
        yield 'bad request' => [400, 'réponse 400'];
    }

    public function testTransportErrorBecomesAiUnavailable(): void
    {
        $client = $this->client(new MockHttpClient(static fn () => throw new TransportException('timeout')));

        $this->expectException(AiUnavailable::class);
        $this->expectExceptionMessage('délai dépassé');
        $client->extract('p', 'i', [AiInput::text('q')], self::SCHEMA);
    }

    public function testAnswerWithoutToolUseIsRefused(): void
    {
        $client = $this->client(new MockHttpClient(new MockResponse('{"content":[{"type":"text","text":"libre"}]}')));

        $this->expectException(AiUnavailable::class);
        $this->expectExceptionMessage('sans contenu structuré');
        $client->extract('p', 'i', [AiInput::text('q')], self::SCHEMA);
    }

    public function testMissingModelFailsAtFirstCall(): void
    {
        $client = new AnthropicClient(new MockHttpClient(), new AiUsageGuard(new ArrayAdapter()), 'key', '');

        $this->expectException(AiMisconfigured::class);
        $this->expectExceptionMessage('YACK_AI_MODEL');
        $client->extract('p', 'i', [AiInput::text('q')], self::SCHEMA);
    }

    public function testMissingKeyFailsAtFirstCall(): void
    {
        $client = new AnthropicClient(new MockHttpClient(), new AiUsageGuard(new ArrayAdapter()), null, 'model-x');

        $this->expectException(AiMisconfigured::class);
        $this->expectExceptionMessage('ANTHROPIC_API_KEY');
        $client->extract('p', 'i', [AiInput::text('q')], self::SCHEMA);
    }

    public function testNoInputIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client(new MockHttpClient())->extract('p', 'i', [], self::SCHEMA);
    }

    public function testCapReachedMeansNoCall(): void
    {
        $calls = 0;
        $http = new MockHttpClient(static function () use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse('{"content":[{"type":"tool_use","input":{}}]}');
        });
        $client = new AnthropicClient($http, new AiUsageGuard(new ArrayAdapter(), '1'), 'test-key', 'model-x');
        $client->extract('p', 'i', [AiInput::text('q')], self::SCHEMA);

        try {
            $client->extract('p', 'i', [AiInput::text('q')], self::SCHEMA);
            $this->fail('Le plafond aurait dû bloquer le deuxième appel.');
        } catch (AiUnavailable $e) {
            $this->assertStringContainsString('plafond', $e->getMessage());
        }
        $this->assertSame(1, $calls);
    }

    private function client(MockHttpClient $http): AnthropicClient
    {
        return new AnthropicClient($http, new AiUsageGuard(new ArrayAdapter()), 'test-key', 'model-x');
    }
}
