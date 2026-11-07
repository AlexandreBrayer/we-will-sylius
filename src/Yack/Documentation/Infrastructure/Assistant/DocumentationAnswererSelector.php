<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Infrastructure\Assistant;

use App\Yack\Common\Domain\AiMode;
use App\Yack\Documentation\Domain\Answer;
use App\Yack\Documentation\Domain\DocumentationAnswerer;
use App\Yack\Documentation\Domain\Question;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The DocumentationAnswerer of the application: demonstration adapter by default, Anthropic only
 * with YACK_AI_ADAPTER=anthropic (docs/adr/0003-llm-anthropic.md).
 */
#[AsAlias(DocumentationAnswerer::class)]
final readonly class DocumentationAnswererSelector implements DocumentationAnswerer
{
    public function __construct(
        private FakeDocumentationAnswerer $fake,
        private AnthropicDocumentationAnswerer $anthropic,
        #[Autowire(env: 'default::YACK_AI_ADAPTER')]
        private ?string $mode = null,
    ) {
    }

    public function answer(Question $question, array $passages): Answer
    {
        return $this->adapter()->answer($question, $passages);
    }

    public function adapter(): DocumentationAnswerer
    {
        return AiMode::fromEnv($this->mode)->isReal() ? $this->anthropic : $this->fake;
    }
}
