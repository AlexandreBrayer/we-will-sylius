<?php

declare(strict_types=1);

namespace App\Tests\Yack\Common\Infrastructure\Mistral;

use App\Yack\Common\Domain\AiInput;
use App\Yack\Common\Domain\AiMisconfigured;
use App\Yack\Common\Domain\AiUnavailable;
use App\Yack\Common\Infrastructure\Anthropic\AiUsageGuard;
use App\Yack\Common\Infrastructure\Mistral\MistralClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 */
#[CoversClass(MistralClient::class)]
final class MistralClientTest extends TestCase
{
    private const SCHEMA = ['type' => 'object', 'properties' => ['kw' => ['type' => 'number']]];

    public function testImageAndTextGiveStructuredAnswer(): void
    {
        $sent = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $sent = ['url' => $url, 'headers' => $options['headers'], 'body' => json_decode($options['body'], true)];

            return new MockResponse((string) json_encode(['choices' => [['message' => ['content' => '{"kw":8}']]]]));
        });

        $answer = $this->client($http)->extract('p', 'Consigne', [AiInput::text('q'), AiInput::image('img', 'image/jpeg')], self::SCHEMA);

        $this->assertSame(['kw' => 8], $answer);
        $this->assertSame('https://api.mistral.ai/v1/chat/completions', $sent['url']);
        $this->assertContains('Authorization: Bearer test-key', $sent['headers']);
        $this->assertSame('Consigne', $sent['body']['messages'][0]['content']);
        $this->assertSame('data:image/jpeg;base64,'.base64_encode('img'), $sent['body']['messages'][1]['content'][1]['image_url']);
        $this->assertSame(self::SCHEMA, $sent['body']['response_format']['json_schema']['schema']);
    }

    public function testPdfGoesThroughOcrFirst(): void
    {
        $urls = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$urls): MockResponse {
            $urls[] = $url;
            if (str_ends_with($url, '/v1/ocr')) {
                return new MockResponse('{"pages":[{"index":0,"markdown":"Longueur max 15 m"}]}');
            }
            $body = json_decode($options['body'], true);
            TestCase::assertStringContainsString("[page 1]\nLongueur max 15 m", $body['messages'][1]['content'][0]['text']);

            return new MockResponse('{"choices":[{"message":{"content":"{\"kw\":1}"}}]}');
        });

        $this->client($http)->extract('p', 'i', [AiInput::pdf('%PDF-1.4 x')], self::SCHEMA);

        $this->assertSame(['https://api.mistral.ai/v1/ocr', 'https://api.mistral.ai/v1/chat/completions'], $urls);
    }

    public function testPdfWithoutOcrModelFailsExplicitly(): void
    {
        $client = new MistralClient(new MockHttpClient(), new AiUsageGuard(new ArrayAdapter()), 'k', 'm', '');

        $this->expectException(AiMisconfigured::class);
        $this->expectExceptionMessage('MISTRAL_OCR_MODEL');
        $client->extract('p', 'i', [AiInput::pdf('%PDF-1.4')], self::SCHEMA);
    }

    public function testErrorsBecomeAiUnavailable(): void
    {
        $client = $this->client(new MockHttpClient(new MockResponse('{}', ['http_code' => 429])));

        $this->expectException(AiUnavailable::class);
        $this->expectExceptionMessage('trop de demandes');
        $client->extract('p', 'i', [AiInput::text('q')], self::SCHEMA);
    }

    public function testNonJsonAnswerIsRefused(): void
    {
        $client = $this->client(new MockHttpClient(new MockResponse('{"choices":[{"message":{"content":"texte libre"}}]}')));

        $this->expectException(AiUnavailable::class);
        $client->extract('p', 'i', [AiInput::text('q')], self::SCHEMA);
    }

    public function testMissingKey(): void
    {
        $client = new MistralClient(new MockHttpClient(), new AiUsageGuard(new ArrayAdapter()), '', 'm');

        $this->expectException(AiMisconfigured::class);
        $this->expectExceptionMessage('MISTRAL_API_KEY');
        $client->extract('p', 'i', [AiInput::text('q')], self::SCHEMA);
    }

    private function client(MockHttpClient $http): MistralClient
    {
        return new MistralClient($http, new AiUsageGuard(new ArrayAdapter()), 'test-key', 'model-x', 'ocr-x');
    }
}
