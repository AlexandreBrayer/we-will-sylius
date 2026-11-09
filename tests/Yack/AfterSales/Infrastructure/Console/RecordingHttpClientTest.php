<?php

declare(strict_types=1);

namespace App\Tests\Yack\AfterSales\Infrastructure\Console;

use App\Yack\AfterSales\Infrastructure\Console\RecordingHttpClient;
use App\Yack\Common\Infrastructure\Ai\JsonValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 */
#[CoversClass(RecordingHttpClient::class)]
final class RecordingHttpClientTest extends TestCase
{
    public function testNothingRecordedBeforeARequest(): void
    {
        $http = new RecordingHttpClient(new MockHttpClient());

        $this->assertSame(0, $http->requestCount());
        $this->assertNull($http->payload());
        $this->assertSame(0, $http->status());
        $this->assertNull($http->body());
        $this->assertNull($http->content());
        $this->assertNull($http->durationMs());
        $this->assertNull($http->transportError());
    }

    public function testRequestIsPassedOnAndRecordedWithoutHeaders(): void
    {
        $seen = [];
        $http = new RecordingHttpClient(new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = $options['headers'];

            return new MockResponse('{"ok":true}', ['http_code' => 200]);
        }));

        $response = $http->request('POST', 'https://example.test/v1', ['headers' => ['x-api-key' => 'secret-key'], 'json' => ['model' => 'm', 'n' => 1]]);

        $this->assertSame('{"ok":true}', $response->getContent());
        $this->assertContains('x-api-key: secret-key', $seen);
        $this->assertSame(1, $http->requestCount());
        $this->assertSame(['model' => 'm', 'n' => 1], $http->payload());
        $this->assertSame(200, $http->status());
        $this->assertSame(['ok' => true], $http->body());
        $this->assertIsInt($http->durationMs());
        $this->assertNull($http->transportError());
        $this->assertStringNotContainsString('secret-key', serialize($http->payload()));
    }

    public function testErrorBodyIsReadableAfterTheCaller(): void
    {
        $http = new RecordingHttpClient(new MockHttpClient(new MockResponse('{"type":"error","error":{"type":"rate_limit_error","message":"lent"}}', ['http_code' => 429])));

        $http->request('POST', 'https://example.test/v1');

        $this->assertSame(429, $http->status());
        $this->assertSame('rate_limit_error', JsonValue::at($http->body(), 'error', 'type'));
    }

    public function testBodyThatIsNotJsonGivesNull(): void
    {
        $http = new RecordingHttpClient(new MockHttpClient(new MockResponse('<html>502</html>', ['http_code' => 502])));
        $http->request('GET', 'https://example.test/');

        $this->assertNull($http->body());
        $this->assertSame('<html>502</html>', $http->content());
    }

    public function testTransportFailureThrownAtRequestIsRecordedAndRethrown(): void
    {
        $http = new RecordingHttpClient(new MockHttpClient(static fn () => throw new TransportException('Connexion refusée')));

        try {
            $http->request('POST', 'https://example.test/v1');
            $this->fail('L\'erreur de transport aurait dû remonter.');
        } catch (TransportException) {
        }

        $this->assertSame(1, $http->requestCount());
        $this->assertSame(0, $http->status());
        $this->assertSame('Connexion refusée', $http->transportError());
    }

    public function testTransportFailureOfTheResponseIsRecorded(): void
    {
        $http = new RecordingHttpClient(new MockHttpClient(new MockResponse('', ['error' => 'Operation timed out after 20001 milliseconds'])));
        $http->request('POST', 'https://example.test/v1');

        $this->assertSame(0, $http->status());
        $this->assertNull($http->content());
        $this->assertStringContainsString('timed out', (string) $http->transportError());
    }

    public function testStreamAndOptionsGoToTheDecoratedClient(): void
    {
        $http = new RecordingHttpClient(new MockHttpClient([new MockResponse('a'), new MockResponse('b')], 'https://example.test'));
        $scoped = $http->withOptions(['base_uri' => 'https://autre.test']);

        $this->assertNotSame($http, $scoped);
        $response = $scoped->request('GET', '/x');
        $this->assertSame('https://autre.test/x', $response->getInfo('url'));
        $chunks = 0;
        foreach ($http->stream($response) as $chunk) {
            ++$chunks;
        }
        $this->assertGreaterThan(0, $chunks);
    }
}
