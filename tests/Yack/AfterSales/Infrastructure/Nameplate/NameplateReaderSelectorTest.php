<?php

declare(strict_types=1);

namespace App\Tests\Yack\AfterSales\Infrastructure\Nameplate;

use App\Yack\AfterSales\Domain\NameplatePhoto;
use App\Yack\AfterSales\Infrastructure\Nameplate\LanguageModelNameplateReader;
use App\Yack\AfterSales\Infrastructure\Nameplate\FakeNameplateReader;
use App\Yack\AfterSales\Infrastructure\Nameplate\NameplateReaderSelector;
use App\Yack\Common\Infrastructure\Image\ExifStripper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NameplateReaderSelector::class)]
final class NameplateReaderSelectorTest extends TestCase
{
    private FakeLanguageModel $languageModel;

    private NameplatePhoto $photo;

    #[\Override]
    protected function setUp(): void
    {
        $this->languageModel = new FakeLanguageModel(['readable' => true, 'serial' => 'YK25EVO08M001234', 'reference' => null, 'model' => 'PAC air/eau RHOSS R32']);
        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($image);
        $this->photo = NameplatePhoto::of((string) ob_get_clean(), 'image/png');
    }

    #[DataProvider('demoModes')]
    public function testDemoReaderByDefault(?string $mode): void
    {
        $reading = $this->selector($mode)->read($this->photo);

        $this->assertTrue($reading->simulated);
        $this->assertSame([], $this->languageModel->calls);
    }

    /** @return \Iterator<string, array{?string}> */
    public static function demoModes(): \Iterator
    {
        yield 'unset' => [null];
        yield 'empty' => [''];
        yield 'fake' => ['fake'];
        yield 'unknown value' => ['openai'];
    }

    #[DataProvider('realModes')]
    public function testRealReaderWhenAProviderIsNamed(string $mode): void
    {
        $reading = $this->selector($mode)->read($this->photo);

        $this->assertFalse($reading->simulated);
        $this->assertSame('YK25EVO08M001234', $reading->serialNumber);
        $this->assertCount(1, $this->languageModel->calls);
    }

    /** @return \Iterator<string, array{string}> */
    public static function realModes(): \Iterator
    {
        yield 'anthropic' => [' Anthropic '];
        yield 'mistral' => ['mistral'];
    }

    private function selector(?string $mode): NameplateReaderSelector
    {
        return new NameplateReaderSelector(
            new FakeNameplateReader(),
            new LanguageModelNameplateReader($this->languageModel, new ExifStripper()),
            $mode,
        );
    }
}
