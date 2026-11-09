<?php

declare(strict_types=1);

namespace App\Tests\Yack\AfterSales\Infrastructure\Console;

use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Réponses enregistrées à la main au format des deux API (aucun appel réel) : ce que lisent AnthropicClient
 * (`content[].tool_use.input`) et MistralClient (`choices[0].message.content`), plus les champs que la commande
 * de debug montre en plus (`model`, `stop_reason` ou `finish_reason`, `usage`) et les corps d'erreur publiés
 * par chaque fournisseur.
 *
 * @internal
 */
final class ProviderResponses
{
    /** @param array<string, mixed> $answer */
    public static function anthropic(array $answer, int $input = 1523, int $output = 87): MockResponse
    {
        return self::json([
            'id' => 'msg_01Test',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'modele-anthropic-test',
            'content' => [['type' => 'tool_use', 'id' => 'toolu_01Test', 'name' => 'reponse', 'input' => $answer]],
            'stop_reason' => 'tool_use',
            'stop_sequence' => null,
            'usage' => ['input_tokens' => $input, 'output_tokens' => $output],
        ]);
    }

    public static function anthropicError(int $status, string $type, string $message): MockResponse
    {
        return self::json(['type' => 'error', 'error' => ['type' => $type, 'message' => $message], 'request_id' => 'req_01Test'], $status);
    }

    /** @param array<string, mixed>|string $answer objet JSON rendu par le modèle, ou texte brut */
    public static function mistral(array|string $answer, int $prompt = 1200, int $completion = 60): MockResponse
    {
        return self::json([
            'id' => 'cmpl-test',
            'object' => 'chat.completion',
            'created' => 1762600000,
            'model' => 'modele-mistral-test',
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => \is_string($answer) ? $answer : (string) json_encode($answer), 'tool_calls' => null],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => $prompt, 'completion_tokens' => $completion, 'total_tokens' => $prompt + $completion],
        ]);
    }

    public static function mistralUnauthorized(): MockResponse
    {
        return self::json(['message' => 'Unauthorized', 'request_id' => 'test'], 401);
    }

    public static function mistralInvalid(string $message): MockResponse
    {
        return self::json(['object' => 'error', 'message' => $message, 'type' => 'invalid_request_error', 'param' => null, 'code' => null], 400);
    }

    /** @param array<string, mixed> $body */
    private static function json(array $body, int $status = 200): MockResponse
    {
        return new MockResponse((string) json_encode($body), ['http_code' => $status, 'response_headers' => ['content-type' => 'application/json']]);
    }
}
