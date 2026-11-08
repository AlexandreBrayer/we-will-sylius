<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Yack\Content\Domain\CmsMediaRefused;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CmsMediaRefused::class)]
final class CmsMediaRefusedTest extends TestCase
{
    public function testEveryRefusalHasItsFrenchMessage(): void
    {
        $this->assertSame([
            'Choisissez un fichier image.',
            'L\'envoi du fichier a échoué : réessayez.',
            'Le fichier est vide.',
            'Fichier trop volumineux (20 Mo maximum).',
            'Les images SVG ne sont pas acceptées : envoyez un JPEG, un PNG ou un WebP.',
            'Format non accepté : JPEG, PNG ou WebP uniquement.',
            'Image illisible : le fichier est peut-être abîmé.',
            'Image trop grande (40 mégapixels au plus) : réduisez-la avant de l\'envoyer.',
            'Le texte alternatif fait au plus 255 caractères.',
        ], [
            CmsMediaRefused::missing()->getMessage(),
            CmsMediaRefused::interrupted()->getMessage(),
            CmsMediaRefused::empty()->getMessage(),
            CmsMediaRefused::tooLarge()->getMessage(),
            CmsMediaRefused::svg()->getMessage(),
            CmsMediaRefused::format()->getMessage(),
            CmsMediaRefused::unreadable()->getMessage(),
            CmsMediaRefused::tooManyPixels()->getMessage(),
            CmsMediaRefused::altTooLong()->getMessage(),
        ]);
    }
}
