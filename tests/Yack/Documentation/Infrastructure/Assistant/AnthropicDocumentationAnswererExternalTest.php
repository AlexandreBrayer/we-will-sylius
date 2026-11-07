<?php

declare(strict_types=1);

namespace App\Tests\Yack\Documentation\Infrastructure\Assistant;

use App\Yack\Common\Infrastructure\Anthropic\AiUsageGuard;
use App\Yack\Common\Infrastructure\Anthropic\AnthropicClient;
use App\Yack\Documentation\Application\RetrievePassages;
use App\Yack\Documentation\Domain\Answer;
use App\Yack\Documentation\Domain\Question;
use App\Yack\Documentation\Infrastructure\Assistant\AnthropicDocumentationAnswerer;
use App\Yack\Documentation\Infrastructure\Corpus\MarkdownDocumentationCorpus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Real call to the Anthropic API on the FICTIF corpus: needs ANTHROPIC_API_KEY and YACK_AI_MODEL in .env.local.
 *
 * @internal
 */
#[Group('external')]
final class AnthropicDocumentationAnswererExternalTest extends TestCase
{
    public function testAnswerFromThePassagesWithItsPage(): void
    {
        $answerer = $this->answerer();
        $question = Question::ask('Quelle est la longueur maximale de liaison hydraulique ?', 'YK-PAE-M08');
        $passages = (new RetrievePassages(new MarkdownDocumentationCorpus(\dirname(__DIR__, 5))))->retrieve($question, 3);

        $answer = $answerer->answer($question, $passages);

        $this->assertStringContainsString('25', $answer->text);
        $this->assertContains(['source' => 'Notice d\'installation Evolys 8 kW (FICTIF)', 'page' => 12], $answer->toArray()['sources']);
        $this->assertFalse($answer->simulated);
    }

    public function testQuestionNotAnsweredByThePassages(): void
    {
        $answerer = $this->answerer();
        $question = Question::ask('Quel est le prix de la PAC ?', 'YK-PAE-M08');
        $passages = (new RetrievePassages(new MarkdownDocumentationCorpus(\dirname(__DIR__, 5))))->retrieve(Question::ask('Code erreur E5 ?', 'YK-PAE-M08'), 3);

        $this->assertSame(Answer::NOT_FOUND, $answerer->answer($question, $passages)->text);
    }

    private function answerer(): AnthropicDocumentationAnswerer
    {
        $key = $_SERVER['ANTHROPIC_API_KEY'] ?? $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY');
        $model = $_SERVER['YACK_AI_MODEL'] ?? $_ENV['YACK_AI_MODEL'] ?? getenv('YACK_AI_MODEL');
        if (!\is_string($key) || '' === $key || !\is_string($model) || '' === $model) {
            $this->markTestSkipped('ANTHROPIC_API_KEY et YACK_AI_MODEL absents.');
        }

        return new AnthropicDocumentationAnswerer(new AnthropicClient(HttpClient::create(), new AiUsageGuard(new ArrayAdapter()), $key, $model));
    }
}
