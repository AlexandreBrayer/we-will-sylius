<?php

declare(strict_types=1);

namespace App\Yack\Common\Infrastructure\Ai;

use App\Yack\Common\Domain\AiMisconfigured;
use App\Yack\Common\Domain\AiMode;
use App\Yack\Common\Domain\LanguageModel;
use App\Yack\Common\Infrastructure\Anthropic\AnthropicClient;
use App\Yack\Common\Infrastructure\Mistral\MistralClient;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The LanguageModel every context receives: the provider chosen by YACK_AI_ADAPTER
 * (anthropic or mistral). In fake mode the contexts use their own demo adapters and never
 * call this; a call anyway is a configuration error, raised explicitly.
 */
#[AsAlias(LanguageModel::class)]
final class LanguageModelSelector implements LanguageModel
{
    private readonly AiMode $mode;

    public function __construct(
        #[Autowire(service: AnthropicClient::class)]
        private readonly LanguageModel $anthropic,
        #[Autowire(service: MistralClient::class)]
        private readonly LanguageModel $mistral,
        #[Autowire(env: 'default::YACK_AI_ADAPTER')]
        ?string $mode = null,
    ) {
        $this->mode = AiMode::fromEnv($mode);
    }

    public function extract(string $purpose, string $instructions, array $inputs, array $schema): array
    {
        return match ($this->mode) {
            AiMode::Anthropic => $this->anthropic->extract($purpose, $instructions, $inputs, $schema),
            AiMode::Mistral => $this->mistral->extract($purpose, $instructions, $inputs, $schema),
            AiMode::Fake => throw new AiMisconfigured('YACK_AI_ADAPTER=fake : aucun fournisseur réel sélectionné.'),
        };
    }
}
