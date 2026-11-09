<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Infrastructure\Nameplate;

use App\Yack\AfterSales\Domain\NameplatePhoto;
use App\Yack\AfterSales\Domain\NameplateReader;
use App\Yack\AfterSales\Domain\NameplateReading;
use App\Yack\Common\Domain\AiMode;
use App\Yack\Common\Domain\AiUnavailable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The NameplateReader of the application: the demo reader unless YACK_AI_ADAPTER names a provider
 * (anthropic or mistral, AiMode::isReal()); the provider itself is chosen by Common.
 *
 * Fallback for the demo plates only: in real mode, when the model is unavailable (AiUnavailable: HTTP error,
 * timeout, daily cap, answer without structured content), a FICTIF plate of public/yack/demo/plaques/
 * (recognised by its marker, FakeNameplateReader::readDemoPlate()) is read by the demo reader, and the
 * reading is simulated. Any other photo keeps the AiUnavailable. No extra call: the demo reader reads the
 * marker only. AiMisconfigured (key or model missing) is never caught. Each fallback is logged as a warning,
 * with the reason only, never anything read on the photo.
 */
#[AsAlias(NameplateReader::class)]
final readonly class NameplateReaderSelector implements NameplateReader
{
    public const FALLBACK_LOG = 'yack.ai nameplate fallback';

    public function __construct(
        private FakeNameplateReader $fake,
        #[Autowire(service: LanguageModelNameplateReader::class)]
        private NameplateReader $real,
        #[Autowire(env: 'default::YACK_AI_ADAPTER')]
        private ?string $mode = null,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function read(NameplatePhoto $photo): NameplateReading
    {
        if (!AiMode::fromEnv($this->mode)->isReal()) {
            return $this->fake->read($photo);
        }

        try {
            return $this->real->read($photo);
        } catch (AiUnavailable $unavailable) {
            $demo = $this->fake->readDemoPlate($photo);
            if (null === $demo) {
                throw $unavailable;
            }

            $this->logger->warning(self::FALLBACK_LOG, [
                'purpose' => LanguageModelNameplateReader::PURPOSE,
                'reason' => $unavailable->getMessage(),
                'reader' => 'demo',
            ]);

            return $demo;
        }
    }
}
