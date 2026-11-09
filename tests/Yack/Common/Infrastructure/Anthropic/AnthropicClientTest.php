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
        $this->assertSame('Lis la plaque.', $sent['body']['system']);
        $this->assertSame(['type' => 'auto'], $sent['body']['tool_choice']);
        $this->assertSame('reponse', $sent['body']['tools'][0]['name']);
        $this->assertSame(self::SCHEMA, $sent['body']['tools'][0]['input_schema']);
        $this->assertArrayNotHasKey('strict', $sent['body']['tools'][0]);
        $this->assertSame(['type' => 'text', 'text' => 'photo'], $sent['body']['messages'][0]['content'][0]);
        $this->assertSame('image', $sent['body']['messages'][0]['content'][1]['type']);
        $this->assertSame(base64_encode('img'), $sent['body']['messages'][0]['content'][1]['source']['data']);
        $this->assertSame(['type' => 'text', 'text' => AnthropicClient::TOOL_RULE], $sent['body']['messages'][0]['content'][2]);
        $this->assertCount(3, $sent['body']['messages'][0]['content']);
    }

    /**
     * Claude Sonnet 5.5 (and Opus 5.5) answer 400 to a forced tool: the client must never send one.
     * With the former payload (`tool_choice` `tool`), this test fails with « réponse 400 ».
     */
    public function testModelThatRejectsForcedToolAnswers(): void
    {
        $http = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            $body = json_decode($options['body'], true);
            $choice = \is_array($body) ? ($body['tool_choice']['type'] ?? 'auto') : 'auto';
            if (\in_array($choice, ['tool', 'any'], true)) {
                return new MockResponse((string) json_encode(['type' => 'error', 'error' => [
                    'type' => 'invalid_request_error',
                    'message' => 'tool_choice: type "tool" and "any" are not supported for this model.',
                ]]), ['http_code' => 400]);
            }

            return new MockResponse((string) json_encode(['model' => 'claude-sonnet-5-5', 'stop_reason' => 'tool_use', 'content' => [
                ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig'],
                ['type' => 'text', 'text' => 'Je relève la plaque.'],
                ['type' => 'tool_use', 'id' => 'toolu_01', 'name' => 'reponse', 'input' => ['serial' => 'MH21ZSW0004242']],
            ]]));
        });

        $answer = $this->client($http)->extract('sav.nameplate', 'Lis la plaque.', [AiInput::image('img', 'image/png')], self::SCHEMA);

        $this->assertSame(['serial' => 'MH21ZSW0004242'], $answer);
    }

    public function testTextThatIsExactlyAJsonObjectIsAccepted(): void
    {
        $client = $this->client(new MockHttpClient(new MockResponse((string) json_encode(['content' => [
            ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig'],
            ['type' => 'text', 'text' => "  {\"serial\": \"YK24HYD11S000321\"}\n"],
        ]]))));

        $this->assertSame(['serial' => 'YK24HYD11S000321'], $client->extract('p', 'i', [AiInput::text('q')], self::SCHEMA));
    }

    #[DataProvider('provideTextsThatAreNotAJsonObject')]
    public function testTextThatIsNotExactlyAJsonObjectIsRefused(string $text): void
    {
        $client = $this->client(new MockHttpClient(new MockResponse((string) json_encode(['content' => [['type' => 'text', 'text' => $text]]]))));

        $this->expectException(AiUnavailable::class);
        $this->expectExceptionMessage('sans contenu structuré');
        $client->extract('p', 'i', [AiInput::text('q')], self::SCHEMA);
    }

    /**
     * @return \Iterator<string, array{string}>
     */
    public static function provideTextsThatAreNotAJsonObject(): \Iterator
    {
        yield 'free text' => ['Le numéro de série est YK24HYD11S000321.'];
        yield 'prose before the object' => ['Voici la réponse : {"serial": "YK24HYD11S000321"}'];
        yield 'code fence' => ["```json\n{\"serial\": \"YK24HYD11S000321\"}\n```"];
        yield 'json list' => ['[{"serial": "YK24HYD11S000321"}]'];
        yield 'truncated object' => ['{"serial": "YK24HYD'];
        yield 'empty' => ['  '];
    }

    public function testToolUseWinsOverJsonText(): void
    {
        $client = $this->client(new MockHttpClient(new MockResponse((string) json_encode(['content' => [
            ['type' => 'text', 'text' => '{"serial": "TEXTE"}'],
            ['type' => 'tool_use', 'name' => 'reponse', 'input' => ['serial' => 'OUTIL']],
        ]]))));

        $this->assertSame(['serial' => 'OUTIL'], $client->extract('p', 'i', [AiInput::text('q')], self::SCHEMA));
    }

    public function testThinkingWithoutToolUseIsRefused(): void
    {
        $client = $this->client(new MockHttpClient(new MockResponse('{"content":[{"type":"thinking","thinking":"","signature":"s"}],"stop_reason":"max_tokens"}')));

        $this->expectException(AiUnavailable::class);
        $this->expectExceptionMessage('sans contenu structuré');
        $client->extract('p', 'i', [AiInput::text('q')], self::SCHEMA);
    }

    public function testPdfIsSentAsDocument(): void
    {
        $payload = $this->client(new MockHttpClient())->payload('i', [AiInput::pdf('%PDF-1.4 x')], self::SCHEMA);

        $this->assertSame('document', $payload['messages'][0]['content'][0]['type']);
        $this->assertSame('application/pdf', $payload['messages'][0]['content'][0]['source']['media_type']);
        $this->assertSame(AnthropicClient::TOOL_RULE, $payload['messages'][0]['content'][1]['text']);
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
