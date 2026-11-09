<?php

declare(strict_types=1);

namespace App\Yack\Common\Infrastructure\Anthropic;

use App\Yack\Common\Domain\AiInput;
use App\Yack\Common\Domain\AiMisconfigured;
use App\Yack\Common\Domain\AiUnavailable;
use App\Yack\Common\Domain\LanguageModel;
use App\Yack\Common\Infrastructure\Ai\JsonValue;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Adapter of LanguageModel on the Anthropic Messages API (docs/adr/0003-llm-anthropic.md).
 *
 * The answer goes through a single tool whose input schema is the expected JSON. The tool is offered
 * with `tool_choice` `auto` and a closing instruction in the user message that requires calling it:
 * recent models (Claude Sonnet 5.5, Opus 5.5) reject a forced tool (`tool` or `any`) with a 400.
 * Only a `tool_use` block, or failing that a text that is exactly a JSON object, is accepted as the
 * answer; anything else is AiUnavailable, never free text. Only the purpose, the HTTP status and the
 * duration are logged, never the content sent or received.
 */
final class AnthropicClient implements LanguageModel
{
    public const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    public const API_VERSION = '2023-06-01';
    public const TIMEOUT = 20.0;
    /** Closing instruction of the user message: the tool is offered (tool_choice auto) and required here. */
    public const TOOL_RULE = 'Réponds uniquement en appelant l\'outil « reponse », une seule fois, avec la réponse structurée attendue : aucun texte libre.';
    private const MAX_TOKENS = 2048;
    private const TOOL = 'reponse';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AiUsageGuard $guard,
        #[Autowire(env: 'default::ANTHROPIC_API_KEY')]
        private readonly ?string $apiKey = null,
        #[Autowire(env: 'default::YACK_AI_MODEL')]
        private readonly ?string $model = null,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function extract(string $purpose, string $instructions, array $inputs, array $schema): array
    {
        if (null === $this->apiKey || '' === trim($this->apiKey)) {
            throw AiMisconfigured::missing('ANTHROPIC_API_KEY');
        }
        if (null === $this->model || '' === trim($this->model)) {
            throw AiMisconfigured::missing('YACK_AI_MODEL');
        }
        if ([] === $inputs) {
            throw new \InvalidArgumentException('Rien à envoyer au modèle.');
        }

        $this->guard->consume();
        $started = microtime(true);

        try {
            $response = $this->httpClient->request('POST', self::ENDPOINT, [
                'timeout' => self::TIMEOUT,
                'max_duration' => self::TIMEOUT,
                'headers' => [
                    'x-api-key' => trim($this->apiKey),
                    'anthropic-version' => self::API_VERSION,
                    'content-type' => 'application/json',
                ],
                'json' => $this->payload($instructions, $inputs, $schema),
            ]);
            $status = $response->getStatusCode();
            $body = 200 === $status ? $response->toArray(false) : null;
        } catch (ExceptionInterface) {
            $this->log($purpose, 0, $started);

            throw AiUnavailable::because('délai dépassé ou réseau');
        }

        $this->log($purpose, $status, $started);
        if (null === $body) {
            throw AiUnavailable::because(429 === $status ? 'trop de demandes' : sprintf('réponse %d', $status));
        }

        return self::structuredAnswer($body) ?? throw AiUnavailable::because('réponse sans contenu structuré');
    }

    /**
     * The structured answer of a Messages API response: the input of the first `tool_use` block, or failing
     * that the text blocks when, together, they are exactly a JSON object (no prose, no code fence around it).
     * Null for anything else (free text, JSON list, truncated JSON, empty content).
     *
     * @return array<string, mixed>|null
     */
    public static function structuredAnswer(mixed $body): ?array
    {
        $texts = [];
        foreach (JsonValue::items(JsonValue::at($body, 'content')) as $block) {
            $type = JsonValue::at($block, 'type');
            $input = 'tool_use' === $type ? JsonValue::object(JsonValue::at($block, 'input')) : null;
            if (null !== $input) {
                return $input;
            }
            $text = 'text' === $type ? JsonValue::at($block, 'text') : null;
            if (\is_string($text)) {
                $texts[] = $text;
            }
        }

        $text = trim(implode("\n", $texts));

        return str_starts_with($text, '{') ? JsonValue::object(json_decode($text, true)) : null;
    }

    /**
     * @param list<AiInput>        $inputs
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    public function payload(string $instructions, array $inputs, array $schema): array
    {
        return [
            'model' => trim((string) $this->model),
            'max_tokens' => self::MAX_TOKENS,
            'system' => $instructions,
            'tools' => [[
                'name' => self::TOOL,
                'description' => 'Rend la réponse structurée attendue.',
                'input_schema' => $schema,
            ]],
            'tool_choice' => ['type' => 'auto'],
            'messages' => [['role' => 'user', 'content' => [...array_map($this->block(...), $inputs), ['type' => 'text', 'text' => self::TOOL_RULE]]]],
        ];
    }

    /** @return array<string, mixed> */
    private function block(AiInput $input): array
    {
        return match ($input->kind) {
            AiInput::TEXT => ['type' => 'text', 'text' => $input->content],
            AiInput::IMAGE => ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $input->mediaType, 'data' => base64_encode($input->content)]],
            default => ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => base64_encode($input->content)]],
        };
    }

    private function log(string $purpose, int $status, float $started): void
    {
        $this->logger->info('yack.ai call', ['purpose' => $purpose, 'status' => $status, 'ms' => (int) round((microtime(true) - $started) * 1000)]);
    }
}
