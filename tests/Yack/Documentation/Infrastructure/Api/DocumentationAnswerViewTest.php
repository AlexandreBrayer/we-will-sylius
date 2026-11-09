<?php

declare(strict_types=1);

namespace App\Tests\Yack\Documentation\Infrastructure\Api;

use App\Yack\Documentation\Domain\Answer;
use App\Yack\Documentation\Domain\Citation;
use App\Yack\Documentation\Infrastructure\Api\DocumentationAnswerView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DocumentationAnswerView::class)]
final class DocumentationAnswerViewTest extends TestCase
{
    public function testAnswerIsExposedAsIs(): void
    {
        $view = DocumentationAnswerView::fromAnswer(Answer::cited('25 m.', [new Citation('Notice Evolys 8 kW (FICTIF)', 12)], simulated: true));

        $this->assertSame('25 m.', $view->answer);
        $this->assertSame([['source' => 'Notice Evolys 8 kW (FICTIF)', 'page' => 12]], $view->sources);
        $this->assertTrue($view->simulated);
    }

    public function testNotFound(): void
    {
        $view = DocumentationAnswerView::fromAnswer(Answer::notFound());

        $this->assertSame('Je ne trouve pas la réponse dans la documentation.', $view->answer);
        $this->assertSame([], $view->sources);
        $this->assertFalse($view->simulated);
    }
}
