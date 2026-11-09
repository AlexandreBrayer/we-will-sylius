<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Infrastructure\Console;

use App\Yack\Common\Infrastructure\Ai\JsonValue;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Client HTTP de la commande de lecture de plaque en mode debug : il passe chaque requête au vrai client sans
 * rien changer, et garde la dernière pour l'affichage (corps JSON envoyé, réponse, durée, erreur de transport).
 * Il ne garde jamais les en-têtes (la clé du fournisseur y voyage) et n'écrit rien dans les journaux.
 *
 * Construit par la commande pour un seul appel ; exclu du conteneur pour ne pas devenir le client HTTP de
 * l'application.
 */
#[Exclude]
final class RecordingHttpClient implements HttpClientInterface
{
    private int $requests = 0;

    /** @var array<string, mixed>|null */
    private ?array $payload = null;

    private ?ResponseInterface $response = null;

    private ?string $failure = null;

    public function __construct(private HttpClientInterface $client)
    {
    }

    /** @param array<string, mixed> $options */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        ++$this->requests;
        $this->payload = JsonValue::object($options['json'] ?? null);
        $this->response = null;
        $this->failure = null;

        try {
            return $this->response = $this->client->request($method, $url, $options);
        } catch (ExceptionInterface $exception) {
            $this->failure = $exception->getMessage();

            throw $exception;
        }
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    /** @param array<string, mixed> $options */
    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->client = $this->client->withOptions($options);

        return $clone;
    }

    /** Nombre de requêtes parties : 0 quand rien n'a été envoyé (clé absente, plafond atteint). */
    public function requestCount(): int
    {
        return $this->requests;
    }

    /**
     * Corps JSON de la dernière requête (pour retrouver l'image réellement envoyée), null sans requête.
     *
     * @return array<string, mixed>|null
     */
    public function payload(): ?array
    {
        return $this->payload;
    }

    /** Statut HTTP de la dernière réponse, 0 sans réponse (délai, réseau). */
    public function status(): int
    {
        if (null === $this->response) {
            return 0;
        }

        try {
            return $this->response->getStatusCode();
        } catch (ExceptionInterface $exception) {
            $this->failure ??= $exception->getMessage();

            return 0;
        }
    }

    /**
     * Corps décodé de la dernière réponse, quel que soit son statut ; null s'il n'est pas du JSON objet.
     *
     * @return array<string, mixed>|null
     */
    public function body(): ?array
    {
        $content = $this->content();

        return null === $content ? null : JsonValue::object(json_decode($content, true));
    }

    /** Corps brut de la dernière réponse, null sans réponse lisible. */
    public function content(): ?string
    {
        if (null === $this->response || 0 === $this->status()) {
            return null;
        }

        try {
            return $this->response->getContent(false);
        } catch (ExceptionInterface $exception) {
            $this->failure ??= $exception->getMessage();

            return null;
        }
    }

    /** Durée de l'échange HTTP mesurée par le client (millisecondes), null sans réponse. */
    public function durationMs(): ?int
    {
        $seconds = $this->response?->getInfo('total_time');

        return \is_float($seconds) || \is_int($seconds) ? (int) round($seconds * 1000) : null;
    }

    /** Erreur de transport (délai, réseau, TLS), null si la réponse est arrivée. Peut contenir un secret : à masquer. */
    public function transportError(): ?string
    {
        $this->status();
        if (null !== $this->failure) {
            return $this->failure;
        }
        $error = $this->response?->getInfo('error');

        return \is_string($error) && '' !== $error ? $error : null;
    }
}
