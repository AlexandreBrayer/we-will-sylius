<?php

declare(strict_types=1);

namespace App\Tests\Yack\Documentation\Infrastructure\Assistant;

use App\Yack\Documentation\Domain\Passage;
use App\Yack\Documentation\Domain\Question;
use App\Yack\Documentation\Infrastructure\Assistant\FakeDocumentationAnswerer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FakeDocumentationAnswerer::class)]
final class FakeDocumentationAnswererTest extends TestCase
{
    public function testTheBestPassageIsTheAnswerWithItsSource(): void
    {
        $answer = (new FakeDocumentationAnswerer())->answer(Question::ask('Longueur ?'), [
            new Passage('Notice PAC', 12, 'Liaison : 25 m.'),
            new Passage('Notice PAC', 13, 'Tubes Ø 32 mm.'),
        ]);

        $this->assertSame(['answer' => 'Liaison : 25 m.', 'sources' => [['source' => 'Notice PAC', 'page' => 12]], 'simulated' => true], $answer->toArray());
    }

    public function testDeterministic(): void
    {
        $passages = [new Passage('Notice PAC', 12, 'Liaison : 25 m.')];
        $answerer = new FakeDocumentationAnswerer();

        $this->assertSame($answerer->answer(Question::ask('a b c'), $passages)->toArray(), $answerer->answer(Question::ask('x y z'), $passages)->toArray());
    }

    public function testNoPassageSaysItDoesNotKnow(): void
    {
        $answer = (new FakeDocumentationAnswerer())->answer(Question::ask('Longueur ?'), []);

        $this->assertFalse($answer->isFound());
        $this->assertTrue($answer->simulated);
    }
}
