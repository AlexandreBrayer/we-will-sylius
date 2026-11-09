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
 * timeout, daily cap, answer without structured content), a FICTIF plate of public/yack/demo/plaques/ is
 * read by the demo reader (FakeNameplateReader::readDemoPlate()), and the reading is simulated. The photo
 * must be one of these files byte for byte (SHA-256): a photo sent by a client with a forged marker, or a
 * demo plate edited in any way, is not a demo plate. Any other photo keeps the AiUnavailable. No extra
 * call: the demo reader reads the marker only. AiMisconfigured (key or model missing) is never caught. Each fallback is logged as a warning,
 * with the reason only, never anything read on the photo.
 */
#[AsAlias(NameplateReader::class)]
final readonly class NameplateReaderSelector implements NameplateReader
{
    public const FALLBACK_LOG = 'yack.ai nameplate fallback';

    /** The FICTIF plates the fallback may read (bin/yack-demo-plaques.php), from this file to public/. */
    public const DEMO_PLATES = __DIR__ . '/../../../../../public/yack/demo/plaques';

    public function __construct(
        private FakeNameplateReader $fake,
        #[Autowire(service: LanguageModelNameplateReader::class)]
        private NameplateReader $real,
        #[Autowire(env: 'default::YACK_AI_ADAPTER')]
        private ?string $mode = null,
        private LoggerInterface $logger = new NullLogger(),
        private string $demoPlates = self::DEMO_PLATES,
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
            $demo = $this->isShippedDemoPlate($photo) ? $this->fake->readDemoPlate($photo) : null;
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

    /** The photo is, byte for byte, one of the *-FICTIF.png files of the demo plates directory. */
    private function isShippedDemoPlate(NameplatePhoto $photo): bool
    {
        $digest = hash('sha256', $photo->bytes);
        foreach (glob($this->demoPlates . '/*-FICTIF.png') ?: [] as $file) {
            if (filesize($file) !== \strlen($photo->bytes)) {
                continue;
            }
            $known = hash_file('sha256', $file);
            if (\is_string($known) && hash_equals($known, $digest)) {
                return true;
            }
        }

        return false;
    }
}
