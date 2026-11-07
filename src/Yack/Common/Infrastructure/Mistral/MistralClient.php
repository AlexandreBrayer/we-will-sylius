<?php

declare(strict_types=1);

namespace App\Yack\Common\Infrastructure\Mistral;

use App\Yack\Common\Domain\AiInput;
use App\Yack\Common\Domain\AiMisconfigured;
use App\Yack\Common\Domain\AiUnavailable;
use App\Yack\Common\Domain\LanguageModel;
use App\Yack\Common\Infrastructure\Anthropic\AiUsageGuard;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Adapter of LanguageModel on the Mistral API (docs/adr/0003-llm-anthropic.md, section Mistral).
 *
 * - Text and images: chat completions, answer forced to the JSON schema (response_format json_schema).
 * - PDF: first read by the OCR endpoint (MISTRAL_OCR_MODEL), its Markdown is then sent as text.
 *
 * Same guards as the Anthropic adapter: daily cap, 20 s, nothing of the content logged.
 * Request formats to be confirmed by MistralClientExternalTest with a real key.
 */
final class MistralClient implements LanguageModel
{
    public const DEFAULT_BASE_URL = 'https://api.mistral.ai';
    public const TIMEOUT = 20.0;
    private const MAX_TOKENS = 2048;

    private readonly string $baseUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AiUsageGuard $guard,
        #[Autowire(env: 'default::MISTRAL_API_KEY')]
        private readonly ?string $apiKey = null,
        #[Autowire(env: 'default::YACK_AI_MODEL')]
        private readonly ?string $model = null,
        #[Autowire(env: 'default::MISTRAL_OCR_MODEL')]
        private readonly ?string $ocrModel = null,
        #[Autowire(env: 'default::MISTRAL_BASE_URL')]
        ?string $baseUrl = null,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->baseUrl = rtrim(null === $baseUrl || '' === trim($baseUrl) ? self::DEFAULT_BASE_URL : trim($baseUrl), '/');
    }

    public function extract(string $purpose, string $instructions, array $inputs, array $schema): array
    {
        $this->require($this->apiKey, 'MISTRAL_API_KEY');
        $this->require($this->model, 'YACK_AI_MODEL');
        if ([] === $inputs) {
            throw new \InvalidArgumentException('Rien à envoyer au modèle.');
        }

        $content = [];
        foreach ($inputs as $input) {
            $content[] = AiInput::PDF === $input->kind
                ? ['type' => 'text', 'text' => $this->ocr($purpose, $input)]
                : $this->part($input);
        }

        $body = $this->call($purpose, '/v1/chat/completions', $this->payload($instructions, $content, $schema));
        $text = $body['choices'][0]['message']['content'] ?? null;
        $answer = \is_string($text) ? json_decode($text, true) : null;
        if (!\is_array($answer)) {
            throw AiUnavailable::because('réponse sans contenu structuré');
        }

        return $answer;
    }

    /**
     * @param list<array<string, mixed>> $content
     * @param array<string, mixed>       $schema
     *
     * @return array<string, mixed>
     */
    public function payload(string $instructions, array $content, array $schema): array
    {
        return [
            'model' => trim((string) $this->model),
            'max_tokens' => self::MAX_TOKENS,
            'temperature' => 0,
            'messages' => [
                ['role' => 'system', 'content' => $instructions],
                ['role' => 'user', 'content' => $content],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'reponse', 'schema' => $schema, 'strict' => true],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function part(AiInput $input): array
    {
        return AiInput::IMAGE === $input->kind
            ? ['type' => 'image_url', 'image_url' => sprintf('data:%s;base64,%s', $input->mediaType, base64_encode($input->content))]
            : ['type' => 'text', 'text' => $input->content];
    }

    private function ocr(string $purpose, AiInput $pdf): string
    {
        $this->require($this->ocrModel, 'MISTRAL_OCR_MODEL');
        $body = $this->call($purpose.'.ocr', '/v1/ocr', [
            'model' => trim((string) $this->ocrModel),
            'document' => ['type' => 'document_url', 'document_url' => 'data:application/pdf;base64,'.base64_encode($pdf->content)],
        ]);

        $pages = [];
        foreach ($body['pages'] ?? [] as $i => $page) {
            if (\is_array($page) && \is_string($page['markdown'] ?? null)) {
                $pages[] = sprintf("[page %d]\n%s", (int) ($page['index'] ?? $i) + 1, $page['markdown']);
            }
        }
        if ([] === $pages) {
            throw AiUnavailable::because('document illisible');
        }

        return implode("\n\n", $pages);
    }

    /**
     * @param array<string, mixed> $json
     *
     * @return array<string, mixed>
     */
    private function call(string $purpose, string $path, array $json): array
    {
        $this->guard->consume();
        $started = microtime(true);

        try {
            $response = $this->httpClient->request('POST', $this->baseUrl.$path, [
                'timeout' => self::TIMEOUT,
                'max_duration' => self::TIMEOUT,
                'headers' => ['Authorization' => 'Bearer '.trim((string) $this->apiKey), 'Accept' => 'application/json'],
                'json' => $json,
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

        return $body;
    }

    private function require(?string $value, string $variable): void
    {
        if (null === $value || '' === trim($value)) {
            throw AiMisconfigured::missing($variable);
        }
    }

    private function log(string $purpose, int $status, float $started): void
    {
        $this->logger->info('yack.ai call', ['provider' => 'mistral', 'purpose' => $purpose, 'status' => $status, 'ms' => (int) round((microtime(true) - $started) * 1000)]);
    }
}
