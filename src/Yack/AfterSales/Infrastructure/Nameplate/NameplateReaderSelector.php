<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Infrastructure\Nameplate;

use App\Yack\AfterSales\Domain\NameplatePhoto;
use App\Yack\AfterSales\Domain\NameplateReader;
use App\Yack\AfterSales\Domain\NameplateReading;
use App\Yack\Common\Domain\AiMode;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The NameplateReader of the application: the demo reader unless YACK_AI_ADAPTER=anthropic.
 */
#[AsAlias(NameplateReader::class)]
final readonly class NameplateReaderSelector implements NameplateReader
{
    public function __construct(
        private FakeNameplateReader $fake,
        private AnthropicNameplateReader $real,
        #[Autowire(env: 'default::YACK_AI_ADAPTER')]
        private ?string $mode = null,
    ) {
    }

    public function read(NameplatePhoto $photo): NameplateReading
    {
        return $this->current()->read($photo);
    }

    private function current(): NameplateReader
    {
        return AiMode::fromEnv($this->mode)->isReal() ? $this->real : $this->fake;
    }
}
