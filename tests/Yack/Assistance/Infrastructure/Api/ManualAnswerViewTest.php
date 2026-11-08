<?php

declare(strict_types=1);

namespace App\Tests\Yack\Assistance\Infrastructure\Api;

use App\Yack\Assistance\Domain\Manual;
use App\Yack\Assistance\Domain\ManualAnswer;
use App\Yack\Assistance\Domain\ManualType;
use App\Yack\Assistance\Infrastructure\Api\ManualAnswerView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ManualAnswerView::class)]
final class ManualAnswerViewTest extends TestCase
{
    public function testEachSourceGivesTheDocumentItsTitleItsPageAndItsLink(): void
    {
        $manual = new Manual('srk-zs-w-utilisation', 'Manuel de l\'utilisateur SRK ZS-W (test)', ManualType::Utilisation, ['fr'], 'https://manuels.example/srk.pdf', true, ['SRK25ZS-W'], []);

        $view = ManualAnswerView::of(ManualAnswer::cited('Lavez le filtre.', [['manual' => $manual, 'page' => 33], ['manual' => $manual, 'page' => 34]], simulated: true));

        $this->assertSame('Lavez le filtre.', $view->answer);
        $this->assertSame([
            ['documentId' => 'srk-zs-w-utilisation', 'title' => 'Manuel de l\'utilisateur SRK ZS-W (test)', 'page' => 33, 'url' => 'https://manuels.example/srk.pdf'],
            ['documentId' => 'srk-zs-w-utilisation', 'title' => 'Manuel de l\'utilisateur SRK ZS-W (test)', 'page' => 34, 'url' => 'https://manuels.example/srk.pdf'],
        ], $view->sources);
        $this->assertTrue($view->simulated);
    }

    public function testNotFoundHasNoSource(): void
    {
        $view = ManualAnswerView::of(ManualAnswer::notFound());

        $this->assertSame([ManualAnswer::NOT_FOUND, [], false], [$view->answer, $view->sources, $view->simulated]);
    }
}
