<?php

declare(strict_types=1);

namespace App\Tests\Yack\AfterSales\Infrastructure\Console;

use App\Yack\AfterSales\Infrastructure\Console\ProviderExchange;
use App\Yack\AfterSales\Infrastructure\Console\RecordingHttpClient;
use App\Yack\Common\Domain\AiInput;
use App\Yack\Common\Domain\AiMode;
use App\Yack\Common\Domain\AiUnavailable;
use App\Yack\Common\Domain\LanguageModel;
use App\Yack\Common\Infrastructure\Anthropic\AiUsageGuard;
use App\Yack\Common\Infrastructure\Anthropic\AnthropicClient;
use App\Yack\Common\Infrastructure\Mistral\MistralClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Les échanges sont produits par les vrais clients de Common (AnthropicClient, MistralClient) sur un client HTTP
 * factice : la requête lue est exactement celle que l'application envoie.
 *
 * @internal
 */
#[CoversClass(ProviderExchange::class)]
final class ProviderExchangeTest extends TestCase
{
    private const ANSWER = ['readable' => true, 'serial' => 'MH21ZSW0004242', 'reference' => 'SRK25ZS-W', 'model' => 'Mural 2,5 kW', 'brand' => 'MHI'];

    public function testAnthropicExchangeIsRead(): void
    {
        $exchange = $this->exchange(AiMode::Anthropic, ProviderResponses::anthropic(self::ANSWER, 1523, 87));

        $this->assertTrue($exchange->sent);
        $this->assertSame(200, $exchange->httpStatus);
        $this->assertSame('modele-x', $exchange->model);
        $this->assertSame('modele-anthropic-test', $exchange->answeredModel);
        $this->assertSame(self::ANSWER, $exchange->answer);
        $this->assertSame(self::ANSWER, $exchange->fields());
        $this->assertSame('tool_use', $exchange->stopReason);
        $this->assertSame(1523, $exchange->inputTokens);
        $this->assertSame(87, $exchange->outputTokens);
        $this->assertSame(['bytes' => \strlen(self::png()), 'width' => 3, 'height' => 2, 'mediaType' => 'image/png'], $exchange->image);
        $this->assertIsInt($exchange->durationMs);
        $this->assertNull($exchange->providerError);
        $this->assertNull($exchange->transportError);
    }

    public function testMistralExchangeIsRead(): void
    {
        $exchange = $this->exchange(AiMode::Mistral, ProviderResponses::mistral(self::ANSWER, 1200, 60));

        $this->assertSame(self::ANSWER, $exchange->answer);
        $this->assertSame('stop', $exchange->stopReason);
        $this->assertSame('modele-mistral-test', $exchange->answeredModel);
        $this->assertSame(1200, $exchange->inputTokens);
        $this->assertSame(60, $exchange->outputTokens);
        $this->assertSame('image/png', $exchange->image['mediaType'] ?? null);
        $this->assertSame(3, $exchange->image['width'] ?? null);
    }

    public function testAnswerWithoutUsageLeavesTokensEmpty(): void
    {
        $exchange = $this->exchange(AiMode::Anthropic, new MockResponse((string) json_encode(['content' => [['type' => 'tool_use', 'input' => ['readable' => false]]]])));

        $this->assertNull($exchange->inputTokens);
        $this->assertNull($exchange->outputTokens);
        $this->assertNull($exchange->stopReason);
        $this->assertNull($exchange->answeredModel);
        $this->assertSame(['readable' => false, 'serial' => null, 'reference' => null, 'model' => null, 'brand' => null], $exchange->fields());
    }

    public function testAnthropicTextWithoutToolIsShownAsText(): void
    {
        $exchange = $this->exchange(AiMode::Anthropic, new MockResponse('{"content":[{"type":"text","text":"Je ne peux pas lire."}],"stop_reason":"end_turn"}'));

        $this->assertSame('Je ne peux pas lire.', $exchange->answer);
        $this->assertSame([], $exchange->fields());
        $this->assertSame('end_turn', $exchange->stopReason);
    }

    public function testAnthropicJsonTextIsReadLikeTheTool(): void
    {
        $exchange = $this->exchange(AiMode::Anthropic, new MockResponse((string) json_encode(['content' => [
            ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig'],
            ['type' => 'text', 'text' => (string) json_encode(self::ANSWER)],
        ], 'stop_reason' => 'end_turn'])));

        $this->assertSame(self::ANSWER, $exchange->answer);
        $this->assertSame(self::ANSWER, $exchange->fields());
    }

    public function testAnthropicWithoutContentGivesNoAnswer(): void
    {
        $this->assertNull($this->exchange(AiMode::Anthropic, new MockResponse('{"content":[]}'))->answer);
    }

    public function testMistralTextThatIsNotJsonIsKeptAsText(): void
    {
        $exchange = $this->exchange(AiMode::Mistral, ProviderResponses::mistral('pas du JSON'));

        $this->assertSame('pas du JSON', $exchange->answer);
        $this->assertSame([], $exchange->fields());
    }

    public function testMistralWithoutChoicesGivesNoAnswer(): void
    {
        $this->assertNull($this->exchange(AiMode::Mistral, new MockResponse('{"choices":[]}'))->answer);
    }

    #[DataProvider('provideProviderErrors')]
    public function testProviderErrorIsReadInClear(AiMode $provider, MockResponse $response, int $status, ?string $error): void
    {
        $exchange = $this->exchange($provider, $response);

        $this->assertSame($status, $exchange->httpStatus);
        $this->assertSame($error, $exchange->providerError);
        $this->assertNull($exchange->answer);
        $this->assertNull($exchange->transportError);
    }

    /**
     * @return \Iterator<string, array{AiMode, MockResponse, int, ?string}>
     */
    public static function provideProviderErrors(): \Iterator
    {
        yield 'anthropic key refused' => [AiMode::Anthropic, ProviderResponses::anthropicError(401, 'authentication_error', 'invalid x-api-key'), 401, 'authentication_error : invalid x-api-key'];
        yield 'anthropic overloaded' => [AiMode::Anthropic, ProviderResponses::anthropicError(529, 'overloaded_error', 'Overloaded'), 529, 'overloaded_error : Overloaded'];
        yield 'mistral key refused' => [AiMode::Mistral, ProviderResponses::mistralUnauthorized(), 401, 'Unauthorized'];
        yield 'mistral invalid request' => [AiMode::Mistral, ProviderResponses::mistralInvalid('Model does not support images'), 400, 'invalid_request_error : Model does not support images'];
        yield 'mistral validation detail' => [AiMode::Mistral, new MockResponse('{"detail":[{"loc":["body","model"],"msg":"required"}]}', ['http_code' => 422]), 422, '[{"loc":["body","model"],"msg":"required"}]'];
        yield 'mistral detail text' => [AiMode::Mistral, new MockResponse('{"detail":"Not found"}', ['http_code' => 404]), 404, 'Not found'];
        yield 'type only' => [AiMode::Anthropic, new MockResponse('{"type":"error"}', ['http_code' => 500]), 500, 'error'];
        yield 'html page' => [AiMode::Mistral, new MockResponse('<html>Bad gateway</html>', ['http_code' => 502]), 502, '<html>Bad gateway</html>'];
        yield 'empty body' => [AiMode::Mistral, new MockResponse('', ['http_code' => 503]), 503, null];
        yield 'blank body' => [AiMode::Anthropic, new MockResponse('   ', ['http_code' => 503]), 503, null];
    }

    public function testTransportFailureIsRead(): void
    {
        $exchange = $this->exchange(AiMode::Anthropic, new MockResponse('', ['error' => 'Operation timed out after 20001 milliseconds']));

        $this->assertTrue($exchange->sent);
        $this->assertSame(0, $exchange->httpStatus);
        $this->assertStringContainsString('timed out', (string) $exchange->transportError);
        $this->assertNull($exchange->providerError);
    }

    public function testNothingSentWhenTheCapIsReached(): void
    {
        $http = new RecordingHttpClient(new MockHttpClient());
        $client = new AnthropicClient($http, new AiUsageGuard(new ArrayAdapter(), '0'), 'test-key', 'modele-x');

        try {
            $client->extract('p', 'i', [AiInput::text('q')], []);
        } catch (AiUnavailable) {
        }

        $exchange = ProviderExchange::fromRecording(AiMode::Anthropic, 'modele-x', $http);

        $this->assertFalse($exchange->sent);
        $this->assertSame(0, $exchange->httpStatus);
        $this->assertNull($exchange->durationMs);
        $this->assertNull($exchange->image);
        $this->assertNull($exchange->transportError);
    }

    public function testImageThatIsNotAnImageHasNoDimensions(): void
    {
        $http = new RecordingHttpClient(new MockHttpClient(ProviderResponses::anthropic(self::ANSWER)));
        (new AnthropicClient($http, new AiUsageGuard(new ArrayAdapter()), 'test-key', 'modele-x'))->extract('p', 'i', [AiInput::image('pas une image', 'image/jpeg')], []);

        $this->assertSame(['bytes' => 13, 'width' => null, 'height' => null, 'mediaType' => 'image/jpeg'], ProviderExchange::fromRecording(AiMode::Anthropic, 'modele-x', $http)->image);
    }

    #[DataProvider('provideHints')]
    public function testStatusHintIsInFrench(int $status, string $expected): void
    {
        $this->assertStringContainsString($expected, ProviderExchange::statusHint($status));
    }

    /**
     * @return \Iterator<string, array{int, string}>
     */
    public static function provideHints(): \Iterator
    {
        yield 'no answer' => [0, 'délai de 20 s'];
        yield 'bad request' => [400, 'ne lit pas les images'];
        yield 'key refused' => [401, 'clé refusée'];
        yield 'forbidden' => [403, 'accès refusé'];
        yield 'unknown model' => [404, 'modèle inconnu'];
        yield 'too large' => [413, 'trop lourde'];
        yield 'invalid' => [422, 'invalide'];
        yield 'quota' => [429, 'quota'];
        yield 'overloaded' => [529, 'surchargé'];
        yield 'server' => [503, 'erreur du fournisseur'];
        yield 'other' => [418, 'réponse 418'];
    }

    private function exchange(AiMode $provider, MockResponse $response): ProviderExchange
    {
        $http = new RecordingHttpClient(new MockHttpClient($response));

        try {
            $this->client($provider, $http)->extract('sav.nameplate', 'Lis la plaque.', [AiInput::image(self::png(), 'image/png')], ['type' => 'object']);
        } catch (AiUnavailable) {
        }

        return ProviderExchange::fromRecording($provider, 'modele-x', $http);
    }

    private function client(AiMode $provider, RecordingHttpClient $http): LanguageModel
    {
        $guard = new AiUsageGuard(new ArrayAdapter());

        return AiMode::Anthropic === $provider
            ? new AnthropicClient($http, $guard, 'test-key', 'modele-x')
            : new MistralClient($http, $guard, 'test-key', 'modele-x');
    }

    private static function png(): string
    {
        $image = imagecreatetruecolor(3, 2);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
