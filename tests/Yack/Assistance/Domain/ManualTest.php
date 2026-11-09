<?php

declare(strict_types=1);

namespace App\Tests\Yack\Assistance\Domain;

use App\Yack\Assistance\Domain\InvalidManual;
use App\Yack\Assistance\Domain\Manual;
use App\Yack\Assistance\Domain\ManualType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Manual::class)]
#[CoversClass(ManualType::class)]
#[CoversClass(InvalidManual::class)]
final class ManualTest extends TestCase
{
    public function testAValidDocument(): void
    {
        $manual = self::manual(text: 'textes/mhi-srk-zs-w-installation.md');

        $this->assertSame('mhi-srk-zs-w-installation', $manual->id);
        $this->assertTrue($manual->hasText());
        $this->assertTrue($manual->isInFrench());
        $this->assertFalse(self::manual(languages: ['en'])->isInFrench());
        $this->assertFalse(self::manual()->hasText());
    }

    /** @return \Iterator<string, array{string, string, list<string>, list<string>, string}> */
    public static function refusals(): \Iterator
    {
        yield 'majuscules' => ['MHI-srk', 'https://mhi.example/a.pdf', ['SRK25ZS-W'], [], 'identifiant hors format'];
        yield 'commence par un tiret' => ['-srk', 'https://mhi.example/a.pdf', ['SRK25ZS-W'], [], 'identifiant hors format'];
        yield 'espace' => ['srk zs', 'https://mhi.example/a.pdf', ['SRK25ZS-W'], [], 'identifiant hors format'];
        yield 'lien http' => ['srk-zs', 'http://mhi.example/a.pdf', ['SRK25ZS-W'], [], 'lien non https'];
        yield 'lien relatif' => ['srk-zs', '/manuels/a.pdf', ['SRK25ZS-W'], [], 'lien non https'];
        yield 'rien de couvert' => ['srk-zs', 'https://mhi.example/a.pdf', [], [], 'aucun modèle ni code'];
    }

    /**
     * @param list<string> $models
     * @param list<string> $codes
     */
    #[DataProvider('refusals')]
    public function testAnInvalidDocumentIsRefusedWithItsId(string $id, string $url, array $models, array $codes, string $reason): void
    {
        $refused = null;
        try {
            new Manual($id, 'Titre', ManualType::Installation, ['fr'], $url, true, $models, $codes);
        } catch (InvalidManual $exception) {
            $refused = $exception;
        }

        $this->assertInstanceOf(InvalidManual::class, $refused);
        $this->assertSame($id, $refused->manualId);
        $this->assertStringContainsString($id, $refused->getMessage());
        $this->assertStringContainsString($reason, $refused->getMessage());
    }

    public function testCatalogCodesAloneAreEnough(): void
    {
        $manual = new Manual('yack-yorabtm', 'Notice', ManualType::Utilisation, ['fr'], 'https://yack.example/n.pdf', true, [], ['YORABTM200']);

        $this->assertSame(['YORABTM200'], $manual->catalogCodes);
    }

    public function testTypesAreOrderedAndLabelled(): void
    {
        $this->assertSame(
            ['installation', 'utilisation', 'technique', 'service', 'fiche_technique', 'pieces', 'telecommande', 'brochure', 'autre'],
            array_map(static fn (ManualType $type): string => $type->value, ManualType::cases()),
        );
        $this->assertSame(0, ManualType::Installation->rank());
        $this->assertSame(8, ManualType::Autre->rank());
        $this->assertSame('Manuel d\'installation', ManualType::Installation->label());
        $this->assertSame(
            ['Manuel d\'utilisation', 'Manuel technique', 'Manuel de service', 'Fiche technique', 'Pièces détachées', 'Télécommande', 'Brochure', 'Autre document'],
            array_map(static fn (ManualType $type): string => $type->label(), \array_slice(ManualType::cases(), 1)),
        );
        $this->assertSame(
            ['utilisation', 'telecommande', 'brochure'],
            array_values(array_map(static fn (ManualType $type): string => $type->value, array_filter(ManualType::cases(), static fn (ManualType $type): bool => $type->isGeneral()))),
        );
    }

    public function testAnUnreadableLibraryHasNoDocumentId(): void
    {
        $exception = InvalidManual::library('JSON invalide');

        $this->assertNull($exception->manualId);
        $this->assertSame('Base de manuels illisible : JSON invalide.', $exception->getMessage());
    }

    /** @param list<string> $languages */
    private static function manual(array $languages = ['fr', 'en'], ?string $text = null): Manual
    {
        return new Manual(
            id: 'mhi-srk-zs-w-installation',
            title: 'Manuel d\'installation SRK20ZS-W, SRK25ZS-W',
            type: ManualType::Installation,
            languages: $languages,
            url: 'https://mhi.example/srk-zs-w.pdf',
            official: true,
            models: ['SRK20ZS-W', 'SRK25ZS-W'],
            catalogCodes: ['SRK25ZS-W'],
            text: $text,
        );
    }
}
