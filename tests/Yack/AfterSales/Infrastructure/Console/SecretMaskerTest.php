<?php

declare(strict_types=1);

namespace App\Tests\Yack\AfterSales\Infrastructure\Console;

use App\Yack\AfterSales\Infrastructure\Console\SecretMasker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SecretMasker::class)]
final class SecretMaskerTest extends TestCase
{
    private const KEY = 'cle-de-test-0123456789';

    #[DataProvider('provideLeaks')]
    public function testSecretsAreMasked(string $text, string $expected): void
    {
        $this->assertSame($expected, (new SecretMasker([self::KEY, null, '']))->mask($text));
    }

    /**
     * @return \Iterator<string, array{string, string}>
     */
    public static function provideLeaks(): \Iterator
    {
        yield 'known key anywhere' => ['clé ' . self::KEY . ' refusée', 'clé [masqué] refusée'];
        yield 'anthropic header' => ['x-api-key: abcdef123456', 'x-api-key: [masqué]'];
        yield 'authorization header with bearer' => ['Authorization: Bearer abc.def-123', 'Authorization: [masqué]'];
        yield 'bearer alone' => ['curl -H Bearer xyz987654', 'curl -H Bearer [masqué]'];
        yield 'json header' => ['{"x-api-key":"abcdef123456"}', '{"x-api-key":"[masqué]"}'];
        yield 'anthropic key format' => ['invalid sk-' . 'ant-api03-AbC_d-9 here', 'invalid [masqué] here'];
        yield 'nothing secret' => ['réponse 401', 'réponse 401'];
    }

    public function testShortValuesAreNotTakenForKeys(): void
    {
        $this->assertSame('abc a b c', (new SecretMasker(['abc', 'a']))->mask('abc a b c'));
    }

    public function testLongestKeyIsMaskedFirst(): void
    {
        $this->assertSame('[masqué]', (new SecretMasker(['secret1', 'secret1-plus-long']))->mask('secret1-plus-long'));
    }

    public function testDecodedJsonIsMaskedAtAnyDepth(): void
    {
        $masker = new SecretMasker([self::KEY]);

        $this->assertSame(
            ['error' => ['message' => 'bad [masqué]', 'code' => 401, 'ok' => false], '[masqué]' => null, 0 => 'x'],
            $masker->maskValue(['error' => ['message' => 'bad ' . self::KEY, 'code' => 401, 'ok' => false], self::KEY => null, 0 => 'x']),
        );
    }
}
