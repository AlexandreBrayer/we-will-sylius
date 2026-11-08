<?php

declare(strict_types=1);

namespace App\Tests\Yack\Assistance\Infrastructure\Api;

use App\Yack\Assistance\Application\ManualSearch;
use App\Yack\Assistance\Domain\Manual;
use App\Yack\Assistance\Domain\ManualType;
use App\Yack\Assistance\Domain\MatchLevel;
use App\Yack\Assistance\Domain\MatchReason;
use App\Yack\Assistance\Domain\ModelReference;
use App\Yack\Assistance\Infrastructure\Api\ManualSearchView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Documents de la vue, sans API Platform : tourne sans vendor/. Le contrat complet est dans `ManualSearchProviderTest`.
 *
 * @internal
 */
#[CoversClass(ManualSearchView::class)]
final class ManualSearchViewTest extends TestCase
{
    public function testADocumentWithTextGivesItsIndexedPagesAndAnotherGivesNull(): void
    {
        $withText = new Manual('srk-zs-w-utilisation', 'Manuel de l\'utilisateur SRK ZS-W (test)', ManualType::Utilisation, ['fr'], 'https://manuels.example/srk.pdf', true,
            ['SRK25ZS-W'], ['SRK25ZS-W'], 'MHI', text: 'textes/srk-zs-w-utilisation.md', indexedPages: '4-8, 10, 33-38');
        $withoutText = new Manual('srk-zs-w-installation', 'Installation manual SRK ZS-W (test)', ManualType::Installation, ['en'], 'https://manuels.example/srk-inst.pdf', true,
            ['SRK25ZS-W'], [], 'MHI');
        $pagesWithoutText = new Manual('srk-zs-w-technique', 'Technical manual SRK ZS-W (test)', ManualType::Technique, ['en'], 'https://manuels.example/srk-tech.pdf', true,
            ['SRK25ZS-W'], [], 'MHI', indexedPages: '45');

        $view = ManualSearchView::of(new ManualSearch('SRK25ZS-W', ModelReference::fromInput('SRK25ZS-W'), 'MHI', ManualSearch::BRAND_FROM_CATALOG, null, null, false, null, [
            ['level' => MatchLevel::Exact, 'reason' => MatchReason::IdenticalReference, 'source' => ManualSearch::SOURCE_REFERENCE, 'manuals' => [$withText, $withoutText, $pagesWithoutText]],
        ]));

        $documents = $view->groups[0]['documents'];
        $this->assertSame(
            [['srk-zs-w-utilisation', true, '4-8, 10, 33-38'], ['srk-zs-w-installation', false, null], ['srk-zs-w-technique', false, null]],
            array_map(static fn (array $document): array => [$document['id'], $document['hasText'], $document['indexedPages']], $documents),
        );
        $this->assertTrue($view->askable);
    }
}
