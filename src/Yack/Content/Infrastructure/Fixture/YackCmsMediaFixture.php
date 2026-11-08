<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Fixture;

use App\Entity\Content\CmsMedia;
use App\Yack\Content\Domain\BlockType;
use App\Yack\Content\Domain\CmsMediaFile;
use App\Yack\Content\Domain\CmsMediaLibrary;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Registers the historical Yack visuals (BlockType::IMAGES, files of the repository under public/) in the
 * media library, under their own path: the pages that show them name a media of the library, and the
 * library opens with the visuals the builder already offers. Their files are never copied nor deleted.
 * A visual already in the library is left as is; a missing or unreadable file stops the fixtures.
 */
final class YackCmsMediaFixture extends AbstractFixture
{
    public function __construct(
        private readonly CmsMediaLibrary $library,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {
    }

    public function getName(): string
    {
        return 'yack_cms_media';
    }

    /**
     * @param array<string, mixed> $options
     */
    public function load(array $options): void
    {
        // One second apart, the first visual the most recent: the list (newest first) follows BlockType::IMAGES.
        $createdAt = new \DateTimeImmutable('-1 hour');
        foreach (BlockType::IMAGES as $position => $path) {
            if ($this->library->exists($path)) {
                continue;
            }

            $bytes = is_file($this->publicDir . '/' . $path) ? (string) file_get_contents($this->publicDir . '/' . $path) : '';
            if ('' === $bytes) {
                throw new \RuntimeException(sprintf('Visuel historique introuvable : public/%s.', $path));
            }

            $this->library->add(new CmsMedia($path, basename($path), CmsMediaFile::read($bytes), createdAt: $createdAt->modify(sprintf('-%d seconds', $position))));
        }
    }
}
