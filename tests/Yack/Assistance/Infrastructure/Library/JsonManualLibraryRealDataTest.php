<?php

declare(strict_types=1);

namespace App\Tests\Yack\Assistance\Infrastructure\Library;

use App\Yack\Assistance\Domain\Manual;
use App\Yack\Assistance\Domain\ManualMatch;
use App\Yack\Assistance\Domain\ManualMatcher;
use App\Yack\Assistance\Domain\MatchLevel;
use App\Yack\Assistance\Domain\ModelReference;
use App\Yack\Assistance\Infrastructure\Library\JsonManualLibrary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * La vraie base versionnée (manuels.json du dossier data/yack/assistance) : chargée sans document refusé, et
 * chaque code catalogue d'un document, saisi tel quel, retrouve ce document au niveau exact. Base absente :
 * test sauté (il ne passe pas sur une base vide).
 *
 * @internal
 */
#[CoversClass(JsonManualLibrary::class)]
final class JsonManualLibraryRealDataTest extends TestCase
{
    private JsonManualLibrary $library;

    #[\Override]
    protected function setUp(): void
    {
        $path = \dirname(__DIR__, 5) . '/data/yack/assistance/manuels.json';
        if (!is_file($path)) {
            self::markTestSkipped('Base des manuels absente : ' . $path);
        }
        $this->library = new JsonManualLibrary($path);
    }

    public function testTheRealLibraryLoadsWithoutRefusedDocument(): void
    {
        $documents = $this->library->documents();
        $ids = array_map(static fn (Manual $manual): string => $manual->id, $documents);

        $this->assertTrue([] !== $documents, 'Base sans document.');
        $this->assertSame(\count($ids), \count(array_unique($ids)));
        $this->library->products();
    }

    public function testEveryCatalogCodeFindsItsDocumentAtTheExactLevel(): void
    {
        $matcher = new ManualMatcher();
        $documents = $this->library->documents();
        $products = $this->library->products();

        $missed = [];
        foreach ($documents as $manual) {
            foreach ($manual->catalogCodes as $code) {
                $reference = ModelReference::fromInput($code);
                $exact = null === $reference ? [] : array_filter(
                    $matcher->match($reference, $documents, $products),
                    static fn (ManualMatch $match): bool => MatchLevel::Exact === $match->level && $match->manual->id === $manual->id,
                );
                if ([] === $exact) {
                    $missed[] = $manual->id . ' : ' . $code;
                }
            }
        }

        $this->assertSame([], $missed);
    }
}
