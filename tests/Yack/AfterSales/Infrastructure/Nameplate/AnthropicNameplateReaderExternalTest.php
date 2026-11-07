<?php

declare(strict_types=1);

namespace App\Tests\Yack\AfterSales\Infrastructure\Nameplate;

use App\Yack\AfterSales\Domain\NameplatePhoto;
use App\Yack\AfterSales\Infrastructure\Nameplate\AnthropicNameplateReader;
use App\Yack\Common\Infrastructure\Anthropic\AiUsageGuard;
use App\Yack\Common\Infrastructure\Anthropic\AnthropicClient;
use App\Yack\Common\Infrastructure\Image\ExifStripper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Real reading of a FICTIF demo plate by the Anthropic API: needs ANTHROPIC_API_KEY and YACK_AI_MODEL in .env.local.
 *
 * @internal
 */
#[Group('external')]
final class AnthropicNameplateReaderExternalTest extends TestCase
{
    public function testDemoPlateIsRead(): void
    {
        $key = $_SERVER['ANTHROPIC_API_KEY'] ?? $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY');
        $model = $_SERVER['YACK_AI_MODEL'] ?? $_ENV['YACK_AI_MODEL'] ?? getenv('YACK_AI_MODEL');
        if (!\is_string($key) || '' === $key || !\is_string($model) || '' === $model) {
            $this->markTestSkipped('ANTHROPIC_API_KEY et YACK_AI_MODEL absents.');
        }

        $reader = new AnthropicNameplateReader(
            new AnthropicClient(HttpClient::create(), new AiUsageGuard(new ArrayAdapter()), $key, $model),
            new ExifStripper(),
        );
        $plate = (string) file_get_contents(\dirname(__DIR__, 5).'/public/yack/demo/plaques/plaque-YK24HYD11S000321-FICTIF.png');

        $reading = $reader->read(NameplatePhoto::of($plate, 'image/png'));

        $this->assertSame('YK24HYD11S000321', $reading->serialNumber);
        $this->assertFalse($reading->simulated);
    }
}
