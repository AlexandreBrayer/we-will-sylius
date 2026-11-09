<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Infrastructure\Console;

use App\Yack\Common\Domain\AiMode;
use App\Yack\Common\Infrastructure\Ai\JsonValue;
use App\Yack\Common\Infrastructure\Anthropic\AnthropicClient;

/**
 * Ce que la commande de lecture de plaque en mode debug montre d'un échange avec le fournisseur, lu dans la
 * requête et la réponse enregistrées (RecordingHttpClient) au format de chaque API :
 * - Anthropic (Messages API) : image en bloc `image` base64, réponse dans le bloc `tool_use` (sinon texte qui est
 *   exactement un objet JSON, comme AnthropicClient::structuredAnswer()), `stop_reason`,
 *   jetons dans `usage.input_tokens` et `usage.output_tokens`, erreur dans `error.type` et `error.message` ;
 * - Mistral (chat completions) : image en `image_url` (URL `data:`), réponse en texte JSON dans
 *   `choices[0].message.content`, `finish_reason`, jetons dans `usage.prompt_tokens` et
 *   `usage.completion_tokens`, erreur dans `message` (ou `detail`) et `type`.
 * Un champ absent de la réponse reste null : rien n'est inventé.
 */
final readonly class ProviderExchange
{
    /**
     * @param array{bytes: int, width: ?int, height: ?int, mediaType: string}|null $image image réellement envoyée
     * @param mixed                                                                $answer réponse brute du modèle (objet JSON décodé, ou texte s'il n'est pas du JSON)
     */
    private function __construct(
        public AiMode $provider,
        public string $model,
        public bool $sent,
        public int $httpStatus,
        public ?int $durationMs,
        public ?array $image,
        public mixed $answer,
        public ?string $stopReason,
        public ?string $answeredModel,
        public ?int $inputTokens,
        public ?int $outputTokens,
        public ?string $providerError,
        public ?string $transportError,
    ) {
    }

    public static function fromRecording(AiMode $provider, string $model, RecordingHttpClient $http): self
    {
        $sent = $http->requestCount() > 0;
        $status = $sent ? $http->status() : 0;
        $body = $sent ? $http->body() : null;
        $ok = 200 === $status;
        $anthropic = AiMode::Anthropic === $provider;

        return new self(
            $provider,
            $model,
            $sent,
            $status,
            $sent ? $http->durationMs() : null,
            self::image($provider, $http->payload()),
            $ok ? ($anthropic ? self::anthropicAnswer($body) : self::mistralAnswer($body)) : null,
            $ok ? self::text($anthropic ? JsonValue::at($body, 'stop_reason') : JsonValue::at($body, 'choices', 0, 'finish_reason')) : null,
            $ok ? self::text(JsonValue::at($body, 'model')) : null,
            self::count(JsonValue::at($body, 'usage', $anthropic ? 'input_tokens' : 'prompt_tokens')),
            self::count(JsonValue::at($body, 'usage', $anthropic ? 'output_tokens' : 'completion_tokens')),
            $sent && !$ok && 0 !== $status ? self::providerError($body, $http->content()) : null,
            $sent && 0 === $status ? ($http->transportError() ?? 'aucune réponse') : null,
        );
    }

    /**
     * Champs du schéma tels que le modèle les a rendus (avant tout contrôle de l'application) ; vide si la
     * réponse n'est pas un objet JSON.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        $answer = JsonValue::object($this->answer);
        if (null === $answer) {
            return [];
        }

        $fields = [];
        foreach (['readable', 'serial', 'reference', 'model', 'brand'] as $key) {
            $fields[$key] = $answer[$key] ?? null;
        }

        return $fields;
    }

    /** Explication en français d'un statut d'erreur du fournisseur. */
    public static function statusHint(int $status): string
    {
        return match (true) {
            0 === $status => 'aucune réponse : délai de 20 s dépassé ou réseau coupé',
            400 === $status => 'requête refusée par le fournisseur (souvent : modèle qui ne lit pas les images, ou image refusée)',
            401 === $status => 'clé refusée : vérifiez la clé posée dans .env.local',
            403 === $status => 'accès refusé à ce modèle ou à ce compte',
            404 === $status => 'modèle inconnu chez ce fournisseur : vérifiez YACK_AI_MODEL ou --model',
            413 === $status => 'image trop lourde pour le fournisseur',
            422 === $status => 'requête jugée invalide par le fournisseur (modèle ou schéma)',
            429 === $status => 'quota ou débit dépassé chez le fournisseur',
            529 === $status => 'fournisseur surchargé, réessayez plus tard',
            $status >= 500 => 'erreur du fournisseur, réessayez plus tard',
            default => sprintf('réponse %d inattendue', $status),
        };
    }

    /**
     * @param array<string, mixed>|null $payload
     *
     * @return array{bytes: int, width: ?int, height: ?int, mediaType: string}|null
     */
    private static function image(AiMode $provider, ?array $payload): ?array
    {
        foreach (JsonValue::items(JsonValue::at($payload, 'messages')) as $message) {
            foreach (JsonValue::items(JsonValue::at($message, 'content')) as $part) {
                $found = AiMode::Anthropic === $provider ? self::anthropicImage($part) : self::mistralImage($part);
                if (null !== $found) {
                    return self::describe(...$found);
                }
            }
        }

        return null;
    }

    /** @return array{0: string, 1: string}|null base64 et type */
    private static function anthropicImage(mixed $part): ?array
    {
        $data = JsonValue::at($part, 'source', 'data');
        $type = JsonValue::at($part, 'source', 'media_type');

        return 'image' === JsonValue::at($part, 'type') && \is_string($data) && \is_string($type) ? [$data, $type] : null;
    }

    /** @return array{0: string, 1: string}|null base64 et type */
    private static function mistralImage(mixed $part): ?array
    {
        $url = JsonValue::at($part, 'image_url');
        $url = \is_string($url) ? $url : JsonValue::at($url, 'url');
        if ('image_url' !== JsonValue::at($part, 'type') || !\is_string($url) || 1 !== preg_match('#^data:([^;,]+);base64,(.*)$#s', $url, $match)) {
            return null;
        }

        return [$match[2], $match[1]];
    }

    /** @return array{bytes: int, width: ?int, height: ?int, mediaType: string} */
    private static function describe(string $base64, string $mediaType): array
    {
        $bytes = (string) base64_decode($base64, true);
        $size = '' === $bytes ? false : @getimagesizefromstring($bytes);

        return [
            'bytes' => \strlen($bytes),
            'width' => false === $size ? null : $size[0],
            'height' => false === $size ? null : $size[1],
            'mediaType' => $mediaType,
        ];
    }

    /** @param array<string, mixed>|null $body */
    private static function anthropicAnswer(?array $body): mixed
    {
        // Même lecture que l'application : bloc tool_use, sinon texte qui est exactement un objet JSON.
        $structured = AnthropicClient::structuredAnswer($body);
        if (null !== $structured) {
            return $structured;
        }

        $texts = [];
        foreach (JsonValue::items(JsonValue::at($body, 'content')) as $block) {
            $text = JsonValue::at($block, 'text');
            if (\is_string($text)) {
                $texts[] = $text;
            }
        }

        return [] === $texts ? null : implode("\n", $texts);
    }

    /** @param array<string, mixed>|null $body */
    private static function mistralAnswer(?array $body): mixed
    {
        $content = JsonValue::at($body, 'choices', 0, 'message', 'content');
        if (!\is_string($content)) {
            return null;
        }

        return JsonValue::object(json_decode($content, true)) ?? $content;
    }

    /** @param array<string, mixed>|null $body */
    private static function providerError(?array $body, ?string $content): ?string
    {
        $type = self::text(JsonValue::at($body, 'error', 'type')) ?? self::text(JsonValue::at($body, 'type'));
        $message = self::text(JsonValue::at($body, 'error', 'message')) ?? self::text(JsonValue::at($body, 'message'));
        $detail = JsonValue::at($body, 'detail');
        $message ??= \is_string($detail) ? $detail : (null === $detail ? null : (string) json_encode($detail, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES));
        // Corps JSON sans message lisible : son type suffit ; corps qui n'est pas du JSON : son début, tel quel.
        if (null === $message && (null !== $body || null === $content)) {
            return $type;
        }
        $message ??= mb_substr(trim((string) $content), 0, 500);
        if ('' === $message) {
            return $type;
        }

        return null === $type ? $message : $type . ' : ' . $message;
    }

    private static function text(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? $value : null;
    }

    private static function count(mixed $value): ?int
    {
        return \is_int($value) && $value >= 0 ? $value : null;
    }
}
