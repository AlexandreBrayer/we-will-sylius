<?php

declare(strict_types=1);

namespace App\Tests\Yack\Documentation\Infrastructure\Assistant;

use App\Yack\Documentation\Domain\Passage;
use App\Yack\Documentation\Domain\Question;
use App\Yack\Documentation\Infrastructure\Assistant\AnthropicDocumentationAnswerer;
use App\Yack\Documentation\Infrastructure\Assistant\DocumentationAnswererSelector;
use App\Yack\Documentation\Infrastructure\Assistant\FakeDocumentationAnswerer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DocumentationAnswererSelector::class)]
final class DocumentationAnswererSelectorTest extends TestCase
{
    #[DataProvider('modes')]
    public function testTheModeChoosesTheAdapter(?string $mode, bool $real): void
    {
        $model = new FakeLanguageModel(['answer' => 'Réponse du modèle.', 'sources' => [['source' => 'Notice', 'page' => 1]]]);
        $selector = new DocumentationAnswererSelector(new FakeDocumentationAnswerer(), new AnthropicDocumentationAnswerer($model), $mode);

        $answer = $selector->answer(Question::ask('question'), [new Passage('Notice', 1, 'Texte du passage.')]);

        $this->assertSame($real ? AnthropicDocumentationAnswerer::class : FakeDocumentationAnswerer::class, $selector->adapter()::class);
        $this->assertSame($real ? 'Réponse du modèle.' : 'Texte du passage.', $answer->text);
        $this->assertSame(!$real, $answer->simulated);
        $this->assertCount($real ? 1 : 0, $model->calls);
    }

    /** @return \Iterator<string, array{?string, bool}> */
    public static function modes(): \Iterator
    {
        yield 'non défini' => [null, false];
        yield 'fake' => ['fake', false];
        yield 'inconnu' => ['autre', false];
        yield 'anthropic' => ['anthropic', true];
        yield 'anthropic en majuscules' => [' ANTHROPIC ', true];
    }
}
