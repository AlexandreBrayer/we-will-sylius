<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Infrastructure\Console;

use App\Yack\AfterSales\Domain\NameplatePhoto;
use App\Yack\AfterSales\Domain\NameplateReader;
use App\Yack\AfterSales\Domain\NameplateReading;
use App\Yack\Common\Domain\AiUnavailable;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Lecteur de plaque de la commande en mode debug : il passe la photo au lecteur décoré sans rien changer et
 * garde la lecture obtenue (ou l'indisponibilité du service), que le cas d'usage IdentifyApplianceFromPhoto
 * ne rend pas. Ainsi une seule lecture, donc un seul appel au fournisseur, donne la lecture et la recherche.
 *
 * Construit par la commande ; exclu du conteneur pour ne jamais devenir le NameplateReader de l'application.
 */
#[Exclude]
final class RecordingNameplateReader implements NameplateReader
{
    private ?NameplateReading $reading = null;

    private ?AiUnavailable $unavailable = null;

    private ?int $durationMs = null;

    public function __construct(private readonly NameplateReader $reader)
    {
    }

    public function read(NameplatePhoto $photo): NameplateReading
    {
        $started = microtime(true);

        try {
            return $this->reading = $this->reader->read($photo);
        } catch (AiUnavailable $unavailable) {
            $this->unavailable = $unavailable;

            throw $unavailable;
        } finally {
            $this->durationMs = (int) round((microtime(true) - $started) * 1000);
        }
    }

    public function reading(): ?NameplateReading
    {
        return $this->reading;
    }

    public function unavailable(): ?AiUnavailable
    {
        return $this->unavailable;
    }

    /** Durée de la lecture (réencodage de la photo et appel au fournisseur), null avant toute lecture. */
    public function durationMs(): ?int
    {
        return $this->durationMs;
    }
}
