<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Meilisearch;

use App\Yack\Catalog\Domain\SearchUnavailable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP API of Meilisearch (MEILISEARCH_URL, MEILISEARCH_KEY). Every failure (unreachable, timeout,
 * error status, unreadable answer) is a SearchUnavailable, so the shop can fall back on the database.
 */
final readonly class MeilisearchClient
{
    /** Search and suggestions: past this delay the shop answers from the database. */
    public const QUERY_TIMEOUT = 1.0;

    /** Index updates (console, catalogue synchronisation). */
    public const INDEXING_TIMEOUT = 30.0;

    private const TASK_POLL_MICROSECONDS = 50_000;

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(MEILISEARCH_URL)%')]
        private string $url,
        #[Autowire('%env(MEILISEARCH_KEY)%')]
        private string $key,
    ) {
    }

    /**
     * @param array<mixed>|null $body JSON body
     *
     * @return array<mixed> decoded answer
     *
     * @throws SearchUnavailable
     */
    public function request(string $method, string $path, ?array $body = null, float $timeout = self::QUERY_TIMEOUT): array
    {
        $options = ['timeout' => $timeout, 'max_duration' => $timeout, 'headers' => ['Accept' => 'application/json']];
        if ('' !== $this->key) {
            $options['auth_bearer'] = $this->key;
        }
        if (null !== $body) {
            $options['json'] = $body;
        }

        try {
            $response = $this->httpClient->request($method, rtrim($this->url, '/') . $path, $options);
            $status = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (ExceptionInterface $exception) {
            throw new SearchUnavailable(sprintf('Meilisearch unreachable (%s %s): %s', $method, $path, $exception->getMessage()), 0, $exception);
        }

        try {
            $answer = '' === $content ? [] : json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new SearchUnavailable(sprintf('Meilisearch answered %s %s with invalid JSON (status %d).', $method, $path, $status), 0, $exception);
        }
        $answer = is_array($answer) ? $answer : [];

        if ($status >= 300) {
            $message = is_string($answer['message'] ?? null) ? $answer['message'] : '';

            throw new SearchUnavailable(sprintf('Meilisearch answered %d to %s %s: %s', $status, $method, $path, $message));
        }

        return $answer;
    }

    /**
     * Waits for the end of the task enqueued by a write (settings, documents).
     *
     * @param array<mixed> $enqueued answer of the write
     *
     * @throws SearchUnavailable when the task fails or lasts longer than the timeout
     */
    public function wait(array $enqueued, float $timeout = self::INDEXING_TIMEOUT): void
    {
        $uid = $enqueued['taskUid'] ?? null;
        if (!is_int($uid)) {
            throw new SearchUnavailable('Meilisearch did not enqueue the task.');
        }

        $deadline = microtime(true) + $timeout;
        do {
            $task = $this->request('GET', '/tasks/' . $uid, null, $timeout);
            $status = $task['status'] ?? null;
            if ('succeeded' === $status) {
                return;
            }
            if ('failed' === $status || 'canceled' === $status) {
                $error = is_array($task['error'] ?? null) && is_string($task['error']['message'] ?? null) ? $task['error']['message'] : $status;

                throw new SearchUnavailable(sprintf('Meilisearch task %d %s: %s', $uid, $status, $error));
            }
            usleep(self::TASK_POLL_MICROSECONDS);
        } while (microtime(true) < $deadline);

        throw new SearchUnavailable(sprintf('Meilisearch task %d still running after %.0f s.', $uid, $timeout));
    }
}
