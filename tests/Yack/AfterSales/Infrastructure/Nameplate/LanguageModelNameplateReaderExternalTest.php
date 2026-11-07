<?php

declare(strict_types=1);

namespace App\Tests\Yack\AfterSales\Infrastructure\Nameplate;

use App\Yack\AfterSales\Domain\NameplatePhoto;
use App\Yack\AfterSales\Infrastructure\Nameplate\LanguageModelNameplateReader;
use App\Yack\Common\Domain\LanguageModel;
use App\Yack\Common\Infrastructure\Anthropic\AiUsageGuard;
use App\Yack\Common\Infrastructure\Anthropic\AnthropicClient;
use App\Yack\Common\Infrastructure\Image\ExifStripper;
use App\Yack\Common\Infrastructure\Mistral\MistralClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Real reading of the FICTIF demo plates by the provider of YACK_AI_ADAPTER (anthropic or mistral): needs
 * YACK_AI_MODEL (a model that reads images) and the key of the provider in .env.local.
 *
 * @internal
 */
#[Group('external')]
final class LanguageModelNameplateReaderExternalTest extends TestCase
{
    #[DataProvider('plates')]
    public function testDemoPlateIsRead(string $file, ?string $serial, ?string $reference): void
    {
        $reader = new LanguageModelNameplateReader($this->languageModel(), new ExifStripper());
        $plate = (string) file_get_contents(\dirname(__DIR__, 5).'/public/yack/demo/plaques/'.$file);

        $reading = $reader->read(NameplatePhoto::of($plate, 'image/png'));

        $this->assertSame($serial, $reading->serialNumber);
        $this->assertSame($reference, $reading->reference);
        $this->assertFalse($reading->simulated);
    }

    /** @return \Iterator<string, array{string, ?string, ?string}> */
    public static function plates(): \Iterator
    {
        yield 'serial of the installed base' => ['plaque-YK24HYD11S000321-FICTIF.png', 'YK24HYD11S000321', 'FDCW-VNX-A'];
        yield 'MHI label, catalog reference' => ['plaque-SRK25ZS-W-FICTIF.png', 'MH21ZSW0004242', 'SRK25ZS-W'];
    }

    public function testBlurredPhotoIsUnreadable(): void
    {
        $reader = new LanguageModelNameplateReader($this->languageModel(), new ExifStripper());
        $plate = (string) file_get_contents(\dirname(__DIR__, 5).'/public/yack/demo/plaques/plaque-illisible-FICTIF.png');

        $this->assertFalse($reader->read(NameplatePhoto::of($plate, 'image/png'))->isReadable());
    }

    private function languageModel(): LanguageModel
    {
        $guard = new AiUsageGuard(new ArrayAdapter());
        $model = self::required('YACK_AI_MODEL');

        return match (strtolower((string) self::env('YACK_AI_ADAPTER'))) {
            'anthropic' => new AnthropicClient(HttpClient::create(), $guard, self::required('ANTHROPIC_API_KEY'), $model),
            'mistral' => new MistralClient(HttpClient::create(), $guard, self::required('MISTRAL_API_KEY'), $model, baseUrl: self::env('MISTRAL_BASE_URL')),
            default => $this->markTestSkipped('YACK_AI_ADAPTER ne désigne ni anthropic ni mistral.'),
        };
    }

    private static function env(string $name): ?string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    private static function required(string $name): string
    {
        return self::env($name) ?? self::markTestSkipped($name.' absent.');
    }
}
